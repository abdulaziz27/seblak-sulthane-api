<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Outlet; // Tambahkan ini
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $outlets = Outlet::all(); // Tambahkan ini

        $orders = Order::with(['outlet'])
            ->when($request->date, function ($query) use ($request) {
                $query->whereDate('created_at', $request->date);
            })
            ->when($request->outlet_id, function ($query) use ($request) {
                $query->where('outlet_id', $request->outlet_id);
            })
            ->latest()
            ->paginate(10);

        return view('pages.orders.index', compact('orders', 'outlets')); // Tambahkan outlets
    }

    public function show($id)
    {
        $order = Order::with(['outlet', 'orderItems.product'])
            ->findOrFail($id);

        return view('pages.orders.show', compact('order'));
    }
}
