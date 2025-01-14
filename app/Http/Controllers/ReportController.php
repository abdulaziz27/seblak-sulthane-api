<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function outletSalesReport(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $outletSales = Order::select('outlets.name as outlet_name', DB::raw('SUM(orders.total_amount) as total_sales'))
            ->join('outlets', 'orders.outlet_id', '=', 'outlets.id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->groupBy('outlets.id', 'outlets.name')
            ->get();

        return view('reports.outlet_sales', compact('outletSales', 'startDate', 'endDate'));
    }

    public function outletPerformanceAnalysis(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $outletPerformance = Outlet::select(
            'outlets.name as outlet_name',
            DB::raw('SUM(orders.total_amount) as revenue'),
            DB::raw('SUM(orders.total_amount) - SUM(orders.total_cost) as gross_profit'),
            DB::raw('COUNT(DISTINCT orders.id) as total_orders'),
            DB::raw('COUNT(DISTINCT orders.customer_id) as total_customers'),
            DB::raw('COUNT(DISTINCT orders.id) / COUNT(DISTINCT orders.customer_id) as conversion_rate')
        )
            ->leftJoin('orders', 'outlets.id', '=', 'orders.outlet_id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->groupBy('outlets.id', 'outlets.name')
            ->get();

        return view('reports.outlet_performance', compact('outletPerformance', 'startDate', 'endDate'));
    }
}
