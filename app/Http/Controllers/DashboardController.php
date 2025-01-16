<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Member;
use App\Models\User;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Filter periode
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth());
        $endDate = $request->input('end_date', Carbon::now());

        // Statistik umum
        $totalRevenue = Order::whereBetween('created_at', [$startDate, $endDate])->sum('total');
        $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])->count();
        $totalMembers = Member::count();
        $totalStaff = User::whereIn('role', ['staff', 'admin'])->count();

        // Performa per outlet
        $outletPerformance = Order::select(
            'outlets.name as outlet_name',
            DB::raw('COUNT(orders.id) as total_orders'),
            DB::raw('SUM(orders.total) as total_revenue'),
            DB::raw('COUNT(DISTINCT orders.member_id) as total_customers')
        )
            ->join('outlets', 'outlets.id', '=', 'orders.outlet_id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->groupBy('outlets.id', 'outlets.name')
            ->get();

        // Trend penjualan harian
        $dailySales = Order::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(total) as total_sales')
        )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return view('pages.dashboard', compact(
            'totalRevenue',
            'totalOrders',
            'totalMembers',
            'totalStaff',
            'outletPerformance',
            'dailySales',
            'startDate',
            'endDate'
        ));
    }
}
