<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\DailyCash;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    //save order
    public function saveOrder(Request $request)
    {
        $user = auth()->user();
        $outletId = $user->outlet_id;

        //validate request
        $request->validate([
            'payment_amount' => 'required',
            'sub_total' => 'required',
            'tax' => 'required',
            'discount' => 'required',
            'discount_amount' => 'required',
            'service_charge' => 'required',
            'total' => 'required',
            'payment_method' => 'required',
            'total_item' => 'required',
            'id_kasir' => 'required',
            'nama_kasir' => 'required',
            'transaction_time' => 'required',
            'order_type' => 'required|in:dine_in,take_away',
            // 'order_items' => 'required'
        ]);

        //create order
        $order = Order::create([
            'payment_amount' => $request->payment_amount,
            'sub_total' => $request->sub_total,
            'tax' => $request->tax,
            'discount' => $request->discount,
            'discount_amount' => $request->discount_amount,
            'service_charge' => $request->service_charge,
            'total' => $request->total,
            'payment_method' => $request->payment_method,
            'total_item' => $request->total_item,
            'id_kasir' => $request->id_kasir,
            'nama_kasir' => $request->nama_kasir,
            'transaction_time' => $request->transaction_time,
            'outlet_id' => $outletId,
            'order_type' => $request->order_type,
            'qris_fee' => 0, // Default value for qris_fee
        ]);

        // Calculate QRIS fee if payment method is QRIS
        if ($request->payment_method === 'qris') {
            $qris_fee = $request->total * 0.003; // 0.3% dari total transaksi
            $order->qris_fee = $qris_fee;
            $order->save(); // Simpan perubahan
        }

        // Load the outlet relationship
        $savedOrder = Order::with('outlet')->findOrFail($order->id);

        //create order items
        foreach ($request->order_items as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['id_product'],
                'quantity' => $item['quantity'],
                'price' => $item['price']
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $savedOrder
        ], 200);
    }

    public function index(Request $request)
    {
        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');
        if ($start_date && $end_date) {
            $start = Carbon::parse($start_date)->startOfDay();
            $end = Carbon::parse($end_date)->endOfDay();

            $orders = Order::whereBetween('created_at', [$start, $end])->with('outlet')->get();
        } else {
            $orders = Order::with('outlet')->get();
        }
        return response()->json([
            'status' => 'success',
            'data' => $orders
        ], 200);
    }

    /**
     * Modified summary method to include cash flow data
     */
    public function summary(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $outletId = $request->input('outlet_id');

        // Base query for orders
        $query = Order::query();

        if ($startDate && $endDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();
            $query->whereBetween('created_at', [$start, $end]);
        }

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        // Existing summary calculations
        $totalRevenue = $query->sum('total');
        $totalDiscount = $query->sum('discount_amount');
        $totalTax = $query->sum('tax');
        $totalServiceCharge = $query->sum('service_charge');
        $totalSubtotal = $query->sum('sub_total');

        // Calculate total QRIS fees using explicit casting to ensure correct summation
        $totalQrisFee = (clone $query)
            ->where('payment_method', 'qris')
            ->selectRaw('COALESCE(SUM(CAST(qris_fee AS DECIMAL(10,2))), 0) as total_fee')
            ->first()->total_fee;

        // Get daily cash data for the period
        $dailyCashQuery = DailyCash::query();

        if ($startDate && $endDate) {
            $dailyCashQuery->whereBetween('date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $dailyCashQuery->where('date', $startDate);
        } else {
            $dailyCashQuery->where('date', Carbon::today()->format('Y-m-d'));
        }

        if ($outletId) {
            $dailyCashQuery->where('outlet_id', $outletId);
        }

        $dailyCashData = $dailyCashQuery->get();

        // Calculate totals from daily cash
        $totalOpeningBalance = $dailyCashData->sum('opening_balance');
        $totalExpenses = $dailyCashData->sum('expenses');

        // Payment method breakdown with improved QRIS fee calculation
        $paymentMethods = clone $query;
        $paymentMethodSummary = $paymentMethods->select(
            'payment_method',
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(total) as total_amount'),
            DB::raw('CASE WHEN payment_method = "qris" THEN COALESCE(SUM(CAST(qris_fee AS DECIMAL(10,2))), 0) ELSE 0 END as qris_fees')
        )
            ->groupBy('payment_method')
            ->get();

        // Convert to a more usable format
        $paymentMethodData = [];
        foreach ($paymentMethodSummary as $method) {
            $paymentMethodData[$method->payment_method] = [
                'count' => $method->count,
                'total' => $method->total_amount,
                'qris_fees' => $method->qris_fees
            ];
        }

        // Total cash sales
        $cashSales = $paymentMethodData['cash']['total'] ?? 0;

        // Total QRIS sales
        $qrisSales = $paymentMethodData['qris']['total'] ?? 0;

        // Beverage sales calculation
        // Assuming we have a category_id for beverages (e.g., 2)
        $beverageQuery = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('products.category_id', 2); // Adjust category ID as needed

        if ($startDate && $endDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();
            $beverageQuery->whereBetween('orders.created_at', [$start, $end]);
        }

        if ($outletId) {
            $beverageQuery->where('orders.outlet_id', $outletId);
        }

        $beverageSales = $beverageQuery->sum(DB::raw('order_items.quantity * order_items.price'));

        // Calculate closing balance
        $closingBalance = $totalOpeningBalance + $cashSales + $qrisSales - $totalExpenses - $totalQrisFee;

        // Prepare daily breakdown data if date range is provided
        $dailyBreakdown = [];

        if ($startDate && $endDate) {
            $currentDate = Carbon::parse($startDate);
            $lastDate = Carbon::parse($endDate);

            while ($currentDate <= $lastDate) {
                $currentDateStr = $currentDate->format('Y-m-d');

                // Get base daily orders query
                $baseDailyOrders = Order::when($outletId, function ($q) use ($outletId) {
                    return $q->where('outlet_id', $outletId);
                })->whereDate('created_at', $currentDateStr);

                // Get daily QRIS fee with explicit casting
                $dailyQrisFee = (clone $baseDailyOrders)
                    ->where('payment_method', 'qris')
                    ->selectRaw('COALESCE(SUM(CAST(qris_fee AS DECIMAL(10,2))), 0) as daily_fee')
                    ->first()->daily_fee;

                // Ambil semua record DailyCash untuk hari ini (bisa lebih dari satu record jika semua outlet)
                $dailyCashRecords = DailyCash::when($outletId, function ($q) use ($outletId) {
                    return $q->where('outlet_id', $outletId);
                })->where('date', $currentDateStr)->get();

                // Jumlahkan opening_balance dan expenses dari semua record pada hari ini
                $dailyOpeningBalance = $dailyCashRecords->sum('opening_balance');
                $dailyExpenses = $dailyCashRecords->sum('expenses');

                // Hitung penjualan untuk masing-masing metode pembayaran
                $dailyCashSales = (clone $baseDailyOrders)->where('payment_method', 'cash')->sum('total');
                $dailyQrisSales = (clone $baseDailyOrders)->where('payment_method', 'qris')->sum('total');

                // Hitung total dan closing balance harian
                $totalSales = $dailyCashSales + $dailyQrisSales;
                $dailyClosingBalance = $dailyOpeningBalance + $totalSales - $dailyExpenses - $dailyQrisFee;

                $dailyBreakdown[] = [
                    'date' => $currentDateStr,
                    'opening_balance' => $dailyOpeningBalance,
                    'expenses' => $dailyExpenses,
                    'cash_sales' => $dailyCashSales,
                    'qris_sales' => $dailyQrisSales,
                    'qris_fee' => $dailyQrisFee,
                    'total_sales' => $totalSales,
                    'closing_balance' => $dailyClosingBalance
                ];

                $currentDate->addDay();
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                // Original summary data
                'total_revenue' => $totalRevenue,
                'total_discount' => $totalDiscount,
                'total_tax' => $totalTax,
                'total_service_charge' => $totalServiceCharge,
                'total_subtotal' => $totalSubtotal,

                // New cash flow data
                'opening_balance' => $totalOpeningBalance,
                'expenses' => $totalExpenses,
                'cash_sales' => $cashSales,
                'qris_sales' => $qrisSales,
                'qris_fee' => $totalQrisFee,
                'beverage_sales' => $beverageSales,
                'closing_balance' => $closingBalance,

                // Payment methods breakdown
                'payment_methods' => $paymentMethodData,

                // Daily breakdown for date ranges
                'daily_breakdown' => $dailyBreakdown
            ]
        ], 200);
    }
}
