<?php

namespace App\Http\Controllers;

use App\Models\MaterialOrder;
use App\Models\MaterialOrderItem;
use App\Models\Order;
use App\Models\Member;
use App\Models\User;
use App\Models\Outlet;
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

        // Initialize $selectedOutlet
        $selectedOutlet = null;

        // Get outlets based on role
        if (
            Auth::user()->role === 'owner'
        ) {
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
        if (
            Auth::user()->role === 'owner'
        ) {
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

        // Trend penjualan harian
        $dailySalesQuery = Order::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(total) as total_sales')
        )
            ->whereBetween('created_at', [$startDate, $endDate]);

        if (Auth::user()->role !== 'owner') {
            $dailySalesQuery->where('outlet_id', Auth::user()->outlet_id);
        } elseif ($request->outlet_id) {
            $dailySalesQuery->where('outlet_id', $request->outlet_id);
        }

        $dailySales = $dailySalesQuery
            ->groupBy('date')
            ->orderBy('date')
            ->get();

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



        // Material orders statistics
        // Get current time periods
        $currentWeekStart = Carbon::now()->startOfWeek();
        $currentWeekEnd = Carbon::now()->endOfWeek();
        $lastWeekStart = Carbon::now()->subWeek()->startOfWeek();
        $lastWeekEnd = Carbon::now()->subWeek()->endOfWeek();

        $currentMonthStart = Carbon::now()->startOfMonth();
        $currentMonthEnd = Carbon::now()->endOfMonth();
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $currentYearStart = Carbon::now()->startOfYear();
        $currentYearEnd = Carbon::now()->endOfYear();
        $lastYearStart = Carbon::now()->subYear()->startOfYear();
        $lastYearEnd = Carbon::now()->subYear()->endOfYear();

        // Base query for material orders
        $materialOrdersQuery = MaterialOrder::select(
            'outlets.id as outlet_id',
            'outlets.name as outlet_name',
            DB::raw('COUNT(material_orders.id) as total_orders'),
            DB::raw('SUM(material_orders.total_amount) as total_amount'),

            // Current periods
            DB::raw('SUM(CASE WHEN material_orders.created_at BETWEEN "' . $currentWeekStart . '" AND "' . $currentWeekEnd . '" THEN 1 ELSE 0 END) as total_orders_this_week'),
            DB::raw('SUM(CASE WHEN material_orders.created_at BETWEEN "' . $currentMonthStart . '" AND "' . $currentMonthEnd . '" THEN 1 ELSE 0 END) as total_orders_this_month'),
            DB::raw('SUM(CASE WHEN material_orders.created_at BETWEEN "' . $currentYearStart . '" AND "' . $currentYearEnd . '" THEN 1 ELSE 0 END) as total_orders_this_year'),

            // Previous periods
            DB::raw('SUM(CASE WHEN material_orders.created_at BETWEEN "' . $lastWeekStart . '" AND "' . $lastWeekEnd . '" THEN 1 ELSE 0 END) as total_orders_last_week'),
            DB::raw('SUM(CASE WHEN material_orders.created_at BETWEEN "' . $lastMonthStart . '" AND "' . $lastMonthEnd . '" THEN 1 ELSE 0 END) as total_orders_last_month'),
            DB::raw('SUM(CASE WHEN material_orders.created_at BETWEEN "' . $lastYearStart . '" AND "' . $lastYearEnd . '" THEN 1 ELSE 0 END) as total_orders_last_year'),

            // Weekly data points (for charts)
            DB::raw('SUM(CASE WHEN DAYOFWEEK(material_orders.created_at) = 1 THEN 1 ELSE 0 END) as sunday_orders'),
            DB::raw('SUM(CASE WHEN DAYOFWEEK(material_orders.created_at) = 2 THEN 1 ELSE 0 END) as monday_orders'),
            DB::raw('SUM(CASE WHEN DAYOFWEEK(material_orders.created_at) = 3 THEN 1 ELSE 0 END) as tuesday_orders'),
            DB::raw('SUM(CASE WHEN DAYOFWEEK(material_orders.created_at) = 4 THEN 1 ELSE 0 END) as wednesday_orders'),
            DB::raw('SUM(CASE WHEN DAYOFWEEK(material_orders.created_at) = 5 THEN 1 ELSE 0 END) as thursday_orders'),
            DB::raw('SUM(CASE WHEN DAYOFWEEK(material_orders.created_at) = 6 THEN 1 ELSE 0 END) as friday_orders'),
            DB::raw('SUM(CASE WHEN DAYOFWEEK(material_orders.created_at) = 7 THEN 1 ELSE 0 END) as saturday_orders')
        )
            ->join('outlets', 'outlets.id', '=', 'material_orders.franchise_id')
            ->whereBetween('material_orders.created_at', [$startDate, $endDate]);

        // Apply user role and outlet filters
        if (Auth::user()->role !== 'owner') {
            $materialOrdersQuery->where('material_orders.franchise_id', Auth::user()->outlet_id);
        } elseif ($request->outlet_id) {
            $materialOrdersQuery->where('material_orders.franchise_id', $request->outlet_id);
        }

        $materialOrdersStats = $materialOrdersQuery
            ->groupBy('outlets.id', 'outlets.name')
            ->get();

        // Calculate percentage changes for periods
        $totalOrdersThisWeek = $materialOrdersStats->sum('total_orders_this_week');
        $totalOrdersLastWeek = $materialOrdersStats->sum('total_orders_last_week');
        $materialOrdersWeekChange = $totalOrdersLastWeek > 0
            ? (($totalOrdersThisWeek - $totalOrdersLastWeek) / $totalOrdersLastWeek) * 100
            : 0;

        $totalOrdersThisMonth = $materialOrdersStats->sum('total_orders_this_month');
        $totalOrdersLastMonth = $materialOrdersStats->sum('total_orders_last_month');
        $materialOrdersMonthChange = $totalOrdersLastMonth > 0
            ? (($totalOrdersThisMonth - $totalOrdersLastMonth) / $totalOrdersLastMonth) * 100
            : 0;

        $totalOrdersThisYear = $materialOrdersStats->sum('total_orders_this_year');
        $totalOrdersLastYear = $materialOrdersStats->sum('total_orders_last_year');
        $materialOrdersYearChange = $totalOrdersLastYear > 0
            ? (($totalOrdersThisYear - $totalOrdersLastYear) / $totalOrdersLastYear) * 100
            : 0;

        // Get weekly data for chart
        $weeklyOrderData = [
            $materialOrdersStats->sum('sunday_orders'),
            $materialOrdersStats->sum('monday_orders'),
            $materialOrdersStats->sum('tuesday_orders'),
            $materialOrdersStats->sum('wednesday_orders'),
            $materialOrdersStats->sum('thursday_orders'),
            $materialOrdersStats->sum('friday_orders'),
            $materialOrdersStats->sum('saturday_orders'),
        ];

        // Get monthly data for charts (last 6 months)
        $monthlyOrderData = [];
        $monthLabels = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            $monthOrders = MaterialOrder::whereBetween('created_at', [$monthStart, $monthEnd]);

            // Apply filters
            if (
                Auth::user()->role !== 'owner'
            ) {
                $monthOrders->where('franchise_id', Auth::user()->outlet_id);
            } elseif ($request->outlet_id) {
                $monthOrders->where('franchise_id', $request->outlet_id);
            }

            $monthlyOrderData[] = $monthOrders->count();
            $monthLabels[] = $month->format('M Y');
        }

        // Get yearly data (last 3 years)
        $yearlyOrderData = [];
        $yearLabels = [];
        for ($i = 2; $i >= 0; $i--) {
            $year = Carbon::now()->subYears($i);
            $yearStart = $year->copy()->startOfYear();
            $yearEnd = $year->copy()->endOfYear();

            $yearOrders = MaterialOrder::whereBetween('created_at', [$yearStart, $yearEnd]);

            // Apply filters
            if (
                Auth::user()->role !== 'owner'
            ) {
                $yearOrders->where('franchise_id', Auth::user()->outlet_id);
            } elseif ($request->outlet_id) {
                $yearOrders->where('franchise_id', $request->outlet_id);
            }

            $yearlyOrderData[] = $yearOrders->count();
            $yearLabels[] = $year->format('Y');
        }

        // Get top ordered materials
        $topMaterialsQuery = MaterialOrderItem::select(
            'raw_materials.id',
            'raw_materials.name as material_name',
            'raw_materials.unit',
            DB::raw('SUM(material_order_items.quantity) as total_quantity'),
            DB::raw('SUM(material_order_items.subtotal) as total_amount')
        )
            ->join('material_orders', 'material_orders.id', '=', 'material_order_items.material_order_id')
            ->join('raw_materials', 'raw_materials.id', '=', 'material_order_items.raw_material_id')
            ->whereBetween('material_orders.created_at', [$startDate, $endDate]);

        if (Auth::user()->role !== 'owner') {
            $topMaterialsQuery->where('material_orders.franchise_id', Auth::user()->outlet_id);
        } elseif ($request->outlet_id) {
            $topMaterialsQuery->where('material_orders.franchise_id', $request->outlet_id);
        }

        $topMaterials = $topMaterialsQuery
            ->groupBy('raw_materials.id', 'raw_materials.name', 'raw_materials.unit')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get();

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
                'outlets',
                'selectedOutlet',
                'topItems',
                'topCustomers',
                'materialOrdersStats',
                'topMaterials',
                'materialOrdersWeekChange',
                'materialOrdersMonthChange',
                'materialOrdersYearChange',
                'weeklyOrderData',
                'monthlyOrderData',
                'monthLabels',
                'yearlyOrderData',
                'yearLabels'
            )
        );
    }
}
