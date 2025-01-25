<?php

namespace App\Http\Controllers;

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



        return view('pages.dashboard', compact(
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
            'topCustomers'
        ));
    }
}
