<?php

namespace App\Http\Controllers;

use App\Models\MaterialOrder;
use App\Models\MaterialOrderItem;
use App\Models\Order;
use App\Models\Member;
use App\Models\User;
use App\Models\Outlet;
use App\Models\RawMaterial;
use App\Models\DailyCash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Filter periode
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth());
        $endDate = $request->input('end_date', Carbon::now());
        $periodType = $request->input('period_type', 'daily'); // Changed default to daily

        // Initialize $selectedOutlet
        $selectedOutlet = null;

        // Get outlets based on role
        if (Auth::user()->role === 'owner') {
            $outlets = Outlet::all();
            if ($request->outlet_id) {
                $selectedOutlet = Outlet::find($request->outlet_id);
            }
        } else {
            $outlets = Outlet::where('id', Auth::user()->outlet_id)->get();
            $selectedOutlet = Auth::user()->outlet;
        }

        // Base queries
        $orderQuery = Order::query();
        $userQuery = User::query();

        // Filter by outlet
        if (Auth::user()->role === 'owner') {
            if ($request->outlet_id) {
                $orderQuery->where('outlet_id', $request->outlet_id);
                $userQuery->where('outlet_id', $request->outlet_id);
            }
        } else {
            $orderQuery->where('outlet_id', Auth::user()->outlet_id);
            $userQuery->where('outlet_id', Auth::user()->outlet_id);
        }

        // Convert dates to proper format
        $startDateFormatted = Carbon::parse($startDate)->startOfDay();
        $endDateFormatted = Carbon::parse($endDate)->endOfDay();

        // Date filter
        $orderQuery->whereBetween('created_at', [$startDateFormatted, $endDateFormatted]);

        // Previous period for comparison
        $previousPeriodStartDate = Carbon::parse($startDateFormatted)->subDays(Carbon::parse($startDateFormatted)->diffInDays($endDateFormatted) + 1);
        $previousPeriodEndDate = Carbon::parse($startDateFormatted)->subDay();

        $previousPeriodOrdersQuery = clone $orderQuery;
        $previousPeriodOrdersQuery->whereBetween('created_at', [$previousPeriodStartDate, $previousPeriodEndDate]);

        // Previous period statistics
        $previousPeriodOrders = $previousPeriodOrdersQuery->count();
        $previousPeriodRevenue = $previousPeriodOrdersQuery->sum('total');
        $previousPeriodMembers = Member::where('created_at', '<', $startDateFormatted)->count();

        // Statistik umum
        $totalRevenue = $orderQuery->sum('total');
        $totalOrders = $orderQuery->count();
        $totalMembers = Member::count();
        $totalStaff = $userQuery->whereIn('role', ['staff', 'admin'])->count();

        // Hitung Profit Margin

        // Hitung member
        $memberOrdersCount = Order::whereBetween('created_at', [$startDateFormatted, $endDateFormatted])
            ->whereNotNull('member_id');

        if (Auth::user()->role !== 'owner') {
            $memberOrdersCount->where('outlet_id', Auth::user()->outlet_id);
        } elseif ($request->outlet_id) {
            $memberOrdersCount->where('outlet_id', $request->outlet_id);
        }

        $memberOrdersCount = $memberOrdersCount->count();
        $memberOrdersPercentage = $totalOrders > 0 ? ($memberOrdersCount / $totalOrders) * 100 : 0;

        // Reset the order query for reuse
        $orderQuery = Order::query();
        if (Auth::user()->role === 'owner' && $request->outlet_id) {
            $orderQuery->where('outlet_id', $request->outlet_id);
        } elseif (Auth::user()->role !== 'owner') {
            $orderQuery->where('outlet_id', Auth::user()->outlet_id);
        }
        $orderQuery->whereBetween('created_at', [$startDateFormatted, $endDateFormatted]);

        // Performa per outlet
        $outletPerformanceQuery = Order::select(
            'outlets.id as outlet_id',
            'outlets.name as outlet_name',
            DB::raw('COUNT(orders.id) as total_orders'),
            DB::raw('SUM(orders.total) as total_revenue'),
            DB::raw('COUNT(DISTINCT orders.member_id) as total_customers')
        )
            ->join('outlets', 'outlets.id', '=', 'orders.outlet_id')
            ->whereBetween('orders.created_at', [$startDateFormatted, $endDateFormatted]);

        if (Auth::user()->role !== 'owner') {
            $outletPerformanceQuery->where('orders.outlet_id', Auth::user()->outlet_id);
        } elseif ($request->outlet_id) {
            $outletPerformanceQuery->where('orders.outlet_id', $request->outlet_id);
        }

        $outletPerformance = $outletPerformanceQuery
            ->groupBy('outlets.id', 'outlets.name')
            ->get();

        // Format daily sales data based on period type
        $dailySalesQuery = Order::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(total) as total_sales')
        )->whereBetween('created_at', [$startDateFormatted, $endDateFormatted]);

        if (Auth::user()->role !== 'owner') {
            $dailySalesQuery->where('outlet_id', Auth::user()->outlet_id);
        } elseif ($request->outlet_id) {
            $dailySalesQuery->where('outlet_id', $request->outlet_id);
        }

        if ($periodType === 'daily') {
            // Daily data - no additional grouping needed
            $dailySalesQuery->groupBy('date');
        } elseif ($periodType === 'weekly') {
            // Weekly data - group by week
            $dailySalesQuery = Order::select(
                DB::raw('YEARWEEK(created_at, 1) as week'),
                DB::raw('MIN(DATE(created_at)) as date'),
                DB::raw('SUM(total) as total_sales')
            )->whereBetween('created_at', [$startDateFormatted, $endDateFormatted]);

            if (Auth::user()->role !== 'owner') {
                $dailySalesQuery->where('outlet_id', Auth::user()->outlet_id);
            } elseif ($request->outlet_id) {
                $dailySalesQuery->where('outlet_id', $request->outlet_id);
            }

            $dailySalesQuery->groupBy('week');
        } elseif ($periodType === 'monthly') {
            // Monthly data - group by month
            $dailySalesQuery = Order::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('MIN(DATE(created_at)) as date'),
                DB::raw('SUM(total) as total_sales')
            )->whereBetween('created_at', [$startDateFormatted, $endDateFormatted]);

            if (Auth::user()->role !== 'owner') {
                $dailySalesQuery->where('outlet_id', Auth::user()->outlet_id);
            } elseif ($request->outlet_id) {
                $dailySalesQuery->where('outlet_id', $request->outlet_id);
            }

            $dailySalesQuery->groupBy('month');
        }

        $dailySales = $dailySalesQuery->orderBy('date')->get();

        // Top selling items
        $topItemsQuery = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->select(
                'products.name as product_name',
                DB::raw('SUM(order_items.quantity) as total_quantity')
            )
            ->whereBetween('orders.created_at', [$startDateFormatted, $endDateFormatted]);

        if (Auth::user()->role !== 'owner') {
            $topItemsQuery->where('orders.outlet_id', Auth::user()->outlet_id);
        } elseif ($request->outlet_id) {
            $topItemsQuery->where('orders.outlet_id', $request->outlet_id);
        }

        $topItems = $topItemsQuery
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_quantity')
            ->limit(5)  // Ambil 5 item teratas
            ->get();

        // Top customers
        $topCustomersQuery = DB::table('orders')
            ->join('members', 'orders.member_id', '=', 'members.id')
            ->select(
                'members.name as member_name',
                'members.phone as member_phone',
                DB::raw('COUNT(orders.id) as total_transactions'),
                DB::raw('SUM(orders.total) as total_spent')
            )
            ->whereBetween('orders.created_at', [$startDateFormatted, $endDateFormatted])
            ->whereNotNull('orders.member_id');

        if (Auth::user()->role !== 'owner') {
            $topCustomersQuery->where('orders.outlet_id', Auth::user()->outlet_id);
        } elseif ($request->outlet_id) {
            $topCustomersQuery->where('orders.outlet_id', $request->outlet_id);
        }

        $topCustomers = $topCustomersQuery
            ->groupBy('members.id', 'members.name', 'members.phone')
            ->orderByDesc('total_spent')
            ->limit(5)
            ->get();

        // Low stock materials
        $lowStockMaterials = RawMaterial::where('stock', '<=', 15)
            ->where('is_active', true)
            ->orderBy('stock')
            ->get();

        // Material orders statistics
        // Get orders based on status
        $materialOrdersQuery = MaterialOrder::query();

        if (Auth::user()->role !== 'owner') {
            $materialOrdersQuery->where('franchise_id', Auth::user()->outlet_id);
        } elseif ($request->outlet_id) {
            $materialOrdersQuery->where('franchise_id', $request->outlet_id);
        }

        $pendingOrdersCount = (clone $materialOrdersQuery)->where('status', 'pending')->count();
        $approvedOrdersCount = (clone $materialOrdersQuery)->where('status', 'approved')->count();
        $deliveredOrdersCount = (clone $materialOrdersQuery)->where('status', 'delivered')->count();

        // Total material cost
        $totalMaterialCost = (clone $materialOrdersQuery)
            ->whereBetween('created_at', [$startDateFormatted, $endDateFormatted])
            ->sum('total_amount');

        // Ongoing orders (pending and approved)
        $ongoingOrders = (clone $materialOrdersQuery)
            ->whereIn('status', ['pending', 'approved'])
            ->with(['franchise', 'user', 'items'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Daily cash data for cash flow - Use formatted date strings
        $startDateStr = Carbon::parse($startDateFormatted)->format('Y-m-d');
        $endDateStr = Carbon::parse($endDateFormatted)->format('Y-m-d');

        // Direct DB query for daily cash - using DB facade for clarity
        $dailyCashRecords = DB::table('daily_cash')
            ->whereBetween('date', [$startDateStr, $endDateStr]);

        if (Auth::user()->role !== 'owner') {
            $dailyCashRecords->where('outlet_id', Auth::user()->outlet_id);
        } elseif ($request->outlet_id) {
            $dailyCashRecords->where('outlet_id', $request->outlet_id);
        }

        $dailyCashData = $dailyCashRecords->get();

        // Calculate cash flow data
        $totalOpeningBalance = $dailyCashData->sum('opening_balance');
        $totalExpenses = $dailyCashData->sum('expenses');

        // Get sales per payment method
        $totalCashSalesQuery = Order::whereBetween('created_at', [$startDateFormatted, $endDateFormatted])
            ->where('payment_method', 'cash');
        $totalQrisSalesQuery = Order::whereBetween('created_at', [$startDateFormatted, $endDateFormatted])
            ->where('payment_method', 'qris');

        if (Auth::user()->role !== 'owner') {
            $totalCashSalesQuery->where('outlet_id', Auth::user()->outlet_id);
            $totalQrisSalesQuery->where('outlet_id', Auth::user()->outlet_id);
        } elseif ($request->outlet_id) {
            $totalCashSalesQuery->where('outlet_id', $request->outlet_id);
            $totalQrisSalesQuery->where('outlet_id', $request->outlet_id);
        }

        $totalCashSales = (clone $totalCashSalesQuery)->sum('total');
        $totalQrisSales = (clone $totalQrisSalesQuery)->sum('total');
        $totalQrisFee = (clone $totalQrisSalesQuery)
            ->selectRaw('COALESCE(SUM(CAST(qris_fee AS DECIMAL(10,2))), 0) as total_fee')
            ->first()
            ->total_fee ?? 0;

        $totalSales = $totalCashSales + $totalQrisSales;

        // Calculate closing balance
        $closingBalance = $totalSales - ($totalOpeningBalance + $totalExpenses + $totalQrisFee);

        // Prepare daily breakdown - generate dates range
        $datesInRange = [];
        $current = Carbon::parse($startDateFormatted)->startOfDay();
        while ($current <= Carbon::parse($endDateFormatted)->endOfDay()) {
            $datesInRange[] = $current->format('Y-m-d');
            $current = $current->addDay();
        }

        // Prepare daily data with running balance
        $dailyData = [];

        foreach ($datesInRange as $index => $date) {
            // PERUBAHAN DI SINI: Dapatkan semua record daily cash untuk tanggal ini
            $dailyCashForDate = $dailyCashData->filter(function ($record) use ($date) {
                return $record->date == $date;
            });

            // Sum up opening balances and expenses from all outlets for this date
            $openingBalance = $dailyCashForDate->sum('opening_balance');
            $expenses = $dailyCashForDate->sum('expenses');

            // Get orders for this date
            $dateOrders = Order::whereDate('created_at', $date);

            if (Auth::user()->role !== 'owner') {
                $dateOrders->where('outlet_id', Auth::user()->outlet_id);
            } elseif ($request->outlet_id) {
                $dateOrders->where('outlet_id', $request->outlet_id);
            }

            $cashSalesForDate = (clone $dateOrders)->where('payment_method', 'cash')->sum('total');
            $qrisOrdersForDate = (clone $dateOrders)->where('payment_method', 'qris');
            $qrisSalesForDate = (clone $qrisOrdersForDate)->sum('total');
            $qrisFeeForDate = (clone $qrisOrdersForDate)
                ->selectRaw('COALESCE(SUM(CAST(qris_fee AS DECIMAL(10,2))), 0) as total_fee')
                ->first()
                ->total_fee ?? 0;
            $totalSalesForDate = $cashSalesForDate + $qrisSalesForDate;

            $dailyClosingBalance = $totalSalesForDate - ($openingBalance + $expenses + $qrisFeeForDate);

            // Store the daily data with the current running balance
            $dailyData[] = [
                'date' => $date,
                'opening_balance' => $openingBalance,
                'expenses' => $expenses,
                'qris_fee' => $qrisFeeForDate,
                'cash_sales' => $cashSalesForDate,
                'qris_sales' => $qrisSalesForDate,
                'total_sales' => $totalSalesForDate,
                'closing_balance' => $dailyClosingBalance
            ];
        }

        // Active staff
        $activeStaff = User::whereIn('role', ['admin', 'staff'])
            ->select('users.*')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $activeStaffCount = count($activeStaff);

        // Menghitung metode pembayaran yang paling populer
        $paymentMethodsQuery = Order::whereBetween('created_at', [$startDateFormatted, $endDateFormatted])
            ->select('payment_method', DB::raw('COUNT(*) as count'))
            ->groupBy('payment_method');

        if (Auth::user()->role !== 'owner') {
            $paymentMethodsQuery->where('outlet_id', Auth::user()->outlet_id);
        } elseif ($request->outlet_id) {
            $paymentMethodsQuery->where('outlet_id', $request->outlet_id);
        }

        $paymentMethods = $paymentMethodsQuery->get();

        // Mencari metode pembayaran terpopuler
        $popularPaymentMethod = null;
        $popularPaymentCount = 0;  // Inisialisasi variabel
        $totalPaymentCount = 0;

        foreach ($paymentMethods as $method) {
            $totalPaymentCount += $method->count;
            if ($method->count > $popularPaymentCount) {
                $popularPaymentCount = $method->count;
                $popularPaymentMethod = $method->payment_method;
            }
        }

        // Menghitung persentase metode pembayaran terpopuler
        $popularPaymentPercentage = $totalPaymentCount > 0 ? ($popularPaymentCount / $totalPaymentCount) * 100 : 0;

        // Membuat mapping nama metode pembayaran yang lebih user-friendly
        $paymentMethodNames = [
            'cash' => 'Tunai',
            'qris' => 'QRIS',
            'debit_card' => 'Kartu Debit',
        ];

        // Mendapatkan nama yang user-friendly
        $popularPaymentMethodName = $paymentMethodNames[$popularPaymentMethod] ?? ucfirst($popularPaymentMethod);

        // Pass all the variables to the view
        return view(
            'pages.dashboard',
            compact(
                'totalRevenue',
                'totalOrders',
                'totalMembers',
                'totalStaff',
                'outletPerformance',
                'dailySales',
                'startDate',
                'endDate',
                'periodType',
                'outlets',
                'selectedOutlet',
                'topItems',
                'topCustomers',
                'previousPeriodOrders',
                'previousPeriodRevenue',
                'previousPeriodMembers',
                'lowStockMaterials',
                'pendingOrdersCount',
                'approvedOrdersCount',
                'deliveredOrdersCount',
                'totalMaterialCost',
                'ongoingOrders',
                'totalOpeningBalance',
                'totalExpenses',
                'totalCashSales',
                'totalQrisSales',
                'totalQrisFee',
                'totalSales',
                'closingBalance',
                'dailyData',
                'activeStaff',
                'activeStaffCount',
                'memberOrdersCount',
                'memberOrdersPercentage',
                'popularPaymentMethod',
                'popularPaymentMethodName',
                'popularPaymentPercentage',
                'popularPaymentCount',
                'totalPaymentCount'
            )
        );
    }
}
