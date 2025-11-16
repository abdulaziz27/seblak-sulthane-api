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
            'notes' => 'nullable|string|max:1000',
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
            'notes' => $request->notes, // Optional notes field
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

        $query = Order::query();

        if ($start_date && $end_date) {
            $start = Carbon::parse($start_date)->startOfDay();
            $end = Carbon::parse($end_date)->endOfDay();

            // Filter berdasarkan created_at karena sudah diselaraskan dengan transaction_time
            $query->whereBetween('created_at', [$start, $end]);
        }

        // Filter tambahan berdasarkan outlet_id jika diperlukan
        if ($request->has('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }

        $orders = $query->with('outlet')->get();

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

        // Get total order count
        $totalOrderCount = $query->count();

        // Get total items ordered
        $totalItemsOrdered = $query->sum('total_item');

        // Existing summary calculations
        $totalRevenue = $query->sum('total');
        $totalDiscount = $query->sum('discount_amount');
        $totalTax = $query->sum('tax');
        $totalServiceCharge = $query->sum('service_charge');
        $totalSubtotal = $query->sum('sub_total');

        // QRIS fees no longer tracked in financial calculations
        $totalQrisFee = 0;

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
            DB::raw('SUM(total) as total_amount')
        )
            ->groupBy('payment_method')
            ->get();

        // Convert to a more usable format
        $paymentMethodData = [];
        foreach ($paymentMethodSummary as $method) {
            $paymentMethodData[$method->payment_method] = [
                'count' => $method->count,
                'total' => $method->total_amount,
                'qris_fees' => 0
            ];
        }

        // Total cash sales
        $cashSales = $paymentMethodData['cash']['total'] ?? 0;

        // Total QRIS sales
        $qrisSales = $paymentMethodData['qris']['total'] ?? 0;

        // Beverage sales calculation with payment method breakdown
        $beverageByPaymentMethod = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('products.category_id', 2) // Assuming category_id 2 is beverages
            ->select(
                'orders.payment_method',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(order_items.quantity * order_items.price) as total_amount')
            );

        if ($startDate && $endDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();
            $beverageByPaymentMethod->whereBetween('orders.created_at', [$start, $end]);
        }

        if ($outletId) {
            $beverageByPaymentMethod->where('orders.outlet_id', $outletId);
        }

        $beverageByPaymentMethod = $beverageByPaymentMethod
            ->groupBy('orders.payment_method')
            ->get();

        // Create a formatted structure for easier consumption in the frontend
        $beveragePaymentBreakdown = [
            'cash' => [
                'quantity' => 0,
                'amount' => 0
            ],
            'qris' => [
                'quantity' => 0,
                'amount' => 0
            ],
            'total' => [
                'quantity' => 0,
                'amount' => 0
            ]
        ];

        foreach ($beverageByPaymentMethod as $item) {
            // Convert payment method to lowercase for consistent key access
            $method = strtolower($item->payment_method);

            // Update specific payment method data
            if (isset($beveragePaymentBreakdown[$method])) {
                $beveragePaymentBreakdown[$method]['quantity'] = $item->total_quantity;
                $beveragePaymentBreakdown[$method]['amount'] = $item->total_amount;
            }

            // Update totals
            $beveragePaymentBreakdown['total']['quantity'] += $item->total_quantity;
            $beveragePaymentBreakdown['total']['amount'] += $item->total_amount;
        }

        // For backward compatibility, keep the original beverageSales variable
        $beverageSales = $beveragePaymentBreakdown['total']['amount'];

        // Food (Makanan + Level) sales calculation (non-beverage categories)
        $foodByPaymentMethod = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('products.category_id', '!=', 2)
            ->select(
                'orders.payment_method',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(order_items.quantity * order_items.price) as total_amount')
            );

        if ($startDate && $endDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();
            $foodByPaymentMethod->whereBetween('orders.created_at', [$start, $end]);
        }

        if ($outletId) {
            $foodByPaymentMethod->where('orders.outlet_id', $outletId);
        }

        $foodByPaymentMethod = $foodByPaymentMethod
            ->groupBy('orders.payment_method')
            ->get();

        $foodPaymentBreakdown = [
            'cash' => [
                'quantity' => 0,
                'amount' => 0,
            ],
            'qris' => [
                'quantity' => 0,
                'amount' => 0,
            ],
            'total' => [
                'quantity' => 0,
                'amount' => 0,
            ],
        ];

        foreach ($foodByPaymentMethod as $item) {
            $method = strtolower($item->payment_method);
            if (isset($foodPaymentBreakdown[$method])) {
                $foodPaymentBreakdown[$method]['quantity'] = $item->total_quantity;
                $foodPaymentBreakdown[$method]['amount'] = $item->total_amount;
            }

            $foodPaymentBreakdown['total']['quantity'] += $item->total_quantity;
            $foodPaymentBreakdown['total']['amount'] += $item->total_amount;
        }

        $foodSales = $foodPaymentBreakdown['total']['amount'];

        // Calculate closing balance
        $effectiveExpenses = $totalOpeningBalance + $totalExpenses;
        $closingBalance = ($cashSales + $qrisSales) - $effectiveExpenses;

        $finalCashClosing = $cashSales - $totalExpenses;

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

                // Get daily order count and total items
                $dailyOrderCount = (clone $baseDailyOrders)->count();
                $dailyItemsOrdered = (clone $baseDailyOrders)->sum('total_item');

                // QRIS fee no longer tracked in system calculations
                $dailyQrisFee = 0;

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
                $dailyEffectiveExpenses = $dailyOpeningBalance + $dailyExpenses;
                $dailyClosingBalance = $totalSales - $dailyEffectiveExpenses;
                $dailyFinalCashClosing = $dailyCashSales - $dailyExpenses;

                // Get beverage breakdown by payment method
                $dailyBeverageByPaymentMethod = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->whereDate('orders.created_at', $currentDateStr)
                    ->where('products.category_id', 2) // Beverage category
                    ->select(
                        'orders.payment_method',
                        DB::raw('SUM(order_items.quantity) as daily_quantity'),
                        DB::raw('SUM(order_items.quantity * order_items.price) as daily_amount')
                    )
                    ->when($outletId, function ($q) use ($outletId) {
                        return $q->where('orders.outlet_id', $outletId);
                    })
                    ->groupBy('orders.payment_method')
                    ->get();

                $dailyBeverageBreakdown = [
                    'cash' => ['quantity' => 0, 'amount' => 0],
                    'qris' => ['quantity' => 0, 'amount' => 0],
                    'total' => ['quantity' => 0, 'amount' => 0]
                ];

                foreach ($dailyBeverageByPaymentMethod as $bevItem) {
                    $method = strtolower($bevItem->payment_method);
                    if (isset($dailyBeverageBreakdown[$method])) {
                        $dailyBeverageBreakdown[$method]['quantity'] = $bevItem->daily_quantity;
                        $dailyBeverageBreakdown[$method]['amount'] = $bevItem->daily_amount;
                    }
                    $dailyBeverageBreakdown['total']['quantity'] += $bevItem->daily_quantity;
                    $dailyBeverageBreakdown['total']['amount'] += $bevItem->daily_amount;
                }

                // Food breakdown per payment method (non-beverage categories)
                $dailyFoodByPaymentMethod = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->whereDate('orders.created_at', $currentDateStr)
                    ->where('products.category_id', '!=', 2)
                    ->select(
                        'orders.payment_method',
                        DB::raw('SUM(order_items.quantity) as daily_quantity'),
                        DB::raw('SUM(order_items.quantity * order_items.price) as daily_amount')
                    )
                    ->when($outletId, function ($q) use ($outletId) {
                        return $q->where('orders.outlet_id', $outletId);
                    })
                    ->groupBy('orders.payment_method')
                    ->get();

                $dailyFoodBreakdown = [
                    'cash' => ['quantity' => 0, 'amount' => 0],
                    'qris' => ['quantity' => 0, 'amount' => 0],
                    'total' => ['quantity' => 0, 'amount' => 0],
                ];

                foreach ($dailyFoodByPaymentMethod as $foodItem) {
                    $method = strtolower($foodItem->payment_method);
                    if (isset($dailyFoodBreakdown[$method])) {
                        $dailyFoodBreakdown[$method]['quantity'] = $foodItem->daily_quantity;
                        $dailyFoodBreakdown[$method]['amount'] = $foodItem->daily_amount;
                    }
                    $dailyFoodBreakdown['total']['quantity'] += $foodItem->daily_quantity;
                    $dailyFoodBreakdown['total']['amount'] += $foodItem->daily_amount;
                }

                $dailyBreakdown[] = [
                    'date' => $currentDateStr,
                    'order_count' => $dailyOrderCount,
                    'items_count' => $dailyItemsOrdered,
                    'opening_balance' => $dailyOpeningBalance,
                    'expenses' => $dailyExpenses,
                    'effective_expenses' => $dailyEffectiveExpenses,
                    'cash_sales' => $dailyCashSales,
                    'qris_sales' => $dailyQrisSales,
                    'qris_fee' => $dailyQrisFee,
                    'total_sales' => $totalSales,
                    'closing_balance' => $dailyClosingBalance,
                    'final_cash_closing' => $dailyFinalCashClosing,
                    'beverage_sales' => $dailyBeverageBreakdown['total']['amount'],
                    'beverage_cash_sales' => $dailyBeverageBreakdown['cash']['amount'],
                    'beverage_qris_sales' => $dailyBeverageBreakdown['qris']['amount'],
                    'beverage_breakdown' => $dailyBeverageBreakdown,
                    'food_sales' => $dailyFoodBreakdown['total']['amount'],
                    'food_cash_sales' => $dailyFoodBreakdown['cash']['amount'],
                    'food_qris_sales' => $dailyFoodBreakdown['qris']['amount'],
                    'food_breakdown' => $dailyFoodBreakdown
                ];

                $currentDate->addDay();
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                // Order count data
                'total_orders' => $totalOrderCount,
                'total_items' => $totalItemsOrdered,

                // Original summary data
                'total_revenue' => $totalRevenue,
                'total_discount' => $totalDiscount,
                'total_tax' => $totalTax,
                'total_service_charge' => $totalServiceCharge,
                'total_subtotal' => $totalSubtotal,

                // New cash flow data
                'opening_balance' => $totalOpeningBalance,
                'expenses' => $totalExpenses,
                'effective_expenses' => $effectiveExpenses,
                'cash_sales' => $cashSales,
                'qris_sales' => $qrisSales,
                'qris_fee' => $totalQrisFee,
                'beverage_sales' => $beverageSales,
                'beverage_cash_sales' => $beveragePaymentBreakdown['cash']['amount'],
                'beverage_qris_sales' => $beveragePaymentBreakdown['qris']['amount'],
                'beverage_breakdown' => $beveragePaymentBreakdown,
                'food_sales' => $foodSales,
                'food_cash_sales' => $foodPaymentBreakdown['cash']['amount'],
                'food_qris_sales' => $foodPaymentBreakdown['qris']['amount'],
                'food_breakdown' => $foodPaymentBreakdown,
                'closing_balance' => $closingBalance,
                'final_cash_closing' => $finalCashClosing,

                // Payment methods breakdown
                'payment_methods' => $paymentMethodData,

                // Daily breakdown for date ranges
                'daily_breakdown' => $dailyBreakdown
            ]
        ], 200);
    }
}
