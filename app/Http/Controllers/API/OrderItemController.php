<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderItemController extends Controller
{
    public function index(Request $request)
    {
        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');

        $query = OrderItem::query();

        $query->select('order_items.*', DB::raw('(SELECT name FROM products WHERE products.id = order_items.product_id) AS product_name'));

        if ($start_date && $end_date) {
            $start = Carbon::parse($start_date)->startOfDay();
            $end = Carbon::parse($end_date)->endOfDay();

            $query->whereBetween('order_items.created_at', [$start, $end]);
        }

        $orderItems = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => $orderItems
        ], 200);
    }
    public function orderSales(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $query = OrderItem::select('order_items.product_id', DB::raw('(SELECT name FROM products WHERE products.id = order_items.product_id) AS product_name'), DB::raw('SUM(order_items.quantity) as total_quantity'))
            ->groupBy('order_items.product_id');
        if ($startDate && $endDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();

            $query->whereBetween('order_items.created_at', [$start, $end]);
        }
        $totalProductSold = $query->orderBy('total_quantity', 'desc')->get();
        return response()->json([
            'status' => 'success',
            'data' => $totalProductSold
        ], 200);
    }
}
