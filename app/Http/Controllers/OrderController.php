<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        // Get outlets based on role
        $outlets = Auth::user()->role === 'owner'
            ? Outlet::all()
            : Outlet::where('id', Auth::user()->outlet_id)->get();

        // Base query
        $query = Order::with(['outlet']);

        // Filter by outlet based on role
        if (Auth::user()->role !== 'owner') {
            $query->where('outlet_id', Auth::user()->outlet_id);
        } elseif ($request->outlet_id) {
            $query->where('outlet_id', $request->outlet_id);
        }

        // Apply date range filter
        if ($request->date_start && $request->date_end) {
            $startDate = Carbon::parse($request->date_start)->startOfDay();
            $endDate = Carbon::parse($request->date_end)->endOfDay();

            $query->whereBetween('created_at', [$startDate, $endDate]);
        }
        // Support for old date parameter (for backward compatibility)
        elseif ($request->date) {
            $query->whereDate('created_at', $request->date);
        }

        // Filter by payment method
        if ($request->payment_method) {
            $query->where('payment_method', $request->payment_method);
        }

        $orders = $query->latest()->paginate(10);

        return view('pages.orders.index', compact('orders', 'outlets'));
    }

    public function show($id)
    {
        $order = Order::with(['outlet', 'orderItems.product'])->findOrFail($id);

        // Check if user has access to this order
        if (Auth::user()->role !== 'owner' && $order->outlet_id !== Auth::user()->outlet_id) {
            return redirect()->route('orders.index')
                ->with('error', 'You do not have permission to view this order');
        }

        return view('pages.orders.show', compact('order'));
    }
}
