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
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date'))->startOfDay() : Carbon::now()->startOfMonth()->startOfDay();
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date'))->endOfDay() : Carbon::now()->endOfDay();
        $periodType = $request->input('period_type', 'daily');

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

        // Date filter
        $orderQuery->whereBetween('created_at', [$startDate, $endDate]);

        // Previous period for comparison
        $previousPeriodStartDate = Carbon::parse($startDate)->subDays(Carbon::parse($startDate)->diffInDays($endDate) + 1);
        $previousPeriodEndDate = Carbon::parse($startDate)->subDay();

        $previousPeriodOrdersQuery = clone $orderQuery;
        $previousPeriodOrdersQuery->whereBetween('created_at', [$previousPeriodStartDate, $previousPeriodEndDate]);

        // Previous period statistics
        $previousPeriodOrders = $previousPeriodOrdersQuery->count();
        $previousPeriodRevenue = $previousPeriodOrdersQuery->sum('total');
        $previousPeriodMembers = Member::where('created_at', '<', $startDate)->count();

        // Statistik umum
        $totalRevenue = $orderQuery->sum('total');
        $totalOrders = $orderQuery->count();
        $totalMembers = Member::count();
        $totalStaff = $userQuery->whereIn('role', ['staff', 'admin'])->count();

        // Reset the order query for reuse
        $orderQuery = Order::query();
        if (Auth::user()->role === 'owner' && $request->outlet_id) {
            $orderQuery->where('outlet_id', $request->outlet_id);
        } elseif (Auth::user()->role !== 'owner') {
            $orderQuery->where('outlet_id', Auth::user()->outlet_id);
        }
        $orderQuery->whereBetween('created_at', [$startDate, $endDate]);

        // Performa per outlet
        $outletPerformanceQuery = Order::select(
            'outlets.id as outlet_id',
            'outlets.name as outlet_name',
            DB::raw('COUNT(orders.id) as total_orders'),
            DB::raw('SUM(orders.total) as total_revenue'),
            DB::raw('COUNT(DISTINCT orders.member_id) as total_customers')
        )
            ->join('outlets', 'outlets.id', '=', 'orders.outlet_id')
            ->whereBetween('orders.created_at', [$startDate, $endDate]);

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
        )->whereBetween('created_at', [$startDate, $endDate]);

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
            )->whereBetween('created_at', [$startDate, $endDate]);

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
            )->whereBetween('created_at', [$startDate, $endDate]);

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
            ->whereBetween('orders.created_at', [$startDate, $endDate]);

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
            ->whereBetween('orders.created_at', [$startDate, $endDate])
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
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total_amount');

        // Ongoing orders (pending and approved)
        $ongoingOrders = (clone $materialOrdersQuery)
            ->whereIn('status', ['pending', 'approved'])
            ->with(['franchise', 'user', 'items'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Daily cash data for cash flow
        $dailyCashQuery = DailyCash::whereBetween('date', [
            Carbon::parse($startDate)->format('Y-m-d'),
            Carbon::parse($endDate)->format('Y-m-d')
        ]);

        if (Auth::user()->role !== 'owner') {
            $dailyCashQuery->where('outlet_id', Auth::user()->outlet_id);
        } elseif ($request->outlet_id) {
            $dailyCashQuery->where('outlet_id', $request->outlet_id);
        }

        $dailyCashRecords = $dailyCashQuery->get();

        // Calculate cash flow data
        $totalOpeningBalance = $dailyCashRecords->sum('opening_balance');
        $totalExpenses = $dailyCashRecords->sum('expenses');

        // Get cash sales
        $totalCashSales = Order::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('payment_method', ['cash', 'qris']);

        if (Auth::user()->role !== 'owner') {
            $totalCashSales->where('outlet_id', Auth::user()->outlet_id);
        } elseif ($request->outlet_id) {
            $totalCashSales->where('outlet_id', $request->outlet_id);
        }

        $totalCashSales = $totalCashSales->sum('total');

        // Get total sales from all payment methods
        $totalSales = Order::whereBetween('created_at', [$startDate, $endDate]);

        if (Auth::user()->role !== 'owner') {
            $totalSales = $totalSales->where('outlet_id', Auth::user()->outlet_id);
        } elseif ($request->outlet_id) {
            $totalSales = $totalSales->where('outlet_id', $request->outlet_id);
        }

        $totalSales = $totalSales->sum('total');

        // Calculate closing balance
        $closingBalance = $totalOpeningBalance + $totalSales - $totalExpenses;

        // Prepare daily breakdown
        $dailyData = [];
        $datesInRange = [];
        $current = Carbon::parse($startDate)->startOfDay();
        while ($current <= Carbon::parse($endDate)->endOfDay()) {
            $datesInRange[] = $current->format('Y-m-d');
            $current = $current->addDay();
        }

        foreach ($datesInRange as $date) {
            // Get daily cash record
            $formattedDate = Carbon::parse($date)->format('Y-m-d');
            $dailyCash = $dailyCashRecords->where('date', $formattedDate)->first();

            // Get orders for this date - using whereDate to ensure we capture the full day
            $dateOrders = Order::whereDate('created_at', $formattedDate);

            if (Auth::user()->role !== 'owner') {
                $dateOrders->where('outlet_id', Auth::user()->outlet_id);
            } elseif ($request->outlet_id) {
                $dateOrders->where('outlet_id', $request->outlet_id);
            }

            $cashSalesForDate = (clone $dateOrders)->where('payment_method', 'cash')->sum('total');
            $qrisSalesForDate = (clone $dateOrders)->where('payment_method', 'qris')->sum('total');

            $dailyData[] = [
                'date' => $date,
                'opening_balance' => $dailyCash ? $dailyCash->opening_balance : 0,
                'expenses' => $dailyCash ? $dailyCash->expenses : 0,
                'cash_sales' => $cashSalesForDate,
                'qris_sales' => $qrisSalesForDate,
                'total_sales' => $cashSalesForDate + $qrisSalesForDate
            ];
        }

        // Active staff
        $activeStaff = User::whereIn('role', ['admin', 'staff'])
            ->select('users.*', DB::raw('RAND() as random_order')) // Random order for demo purposes
            ->orderBy('random_order')
            ->limit(5)
            ->get();

        $activeStaffCount = count($activeStaff);

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
                'totalSales',
                'closingBalance',
                'dailyData',
                'activeStaff',
                'activeStaffCount'
            )
        );
    }
}
