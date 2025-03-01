<?php

namespace App\Http\Controllers;

use App\Models\MaterialOrder;
use App\Models\MaterialOrderItem;
use App\Models\RawMaterial;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class MaterialOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = MaterialOrder::with(['franchise', 'user']);

        // Filter by outlet based on role
        if (Auth::user()->role !== 'owner') {
            $query->where('franchise_id', Auth::user()->outlet_id);
        } elseif ($request->franchise_id) {
            $query->where('franchise_id', $request->franchise_id);
        }

        // Filter by status
        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        // Handle date filtering with multiple options

        // 1. Date Range Button (daterange-btn)
        if ($request->filled('date_start') && $request->filled('date_end')) {
            $startDate = Carbon::parse($request->date_start)->startOfDay();
            $endDate = Carbon::parse($request->date_end)->endOfDay();
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        // 2. Single Date Picker (datepicker)
        elseif ($request->filled('single_date')) {
            $date = Carbon::parse($request->single_date);
            $query->whereDate('created_at', $date);
        }

        // 3. Date Range Picker (daterange-cus)
        elseif ($request->filled('date_range')) {
            try {
                $dates = explode(' - ', $request->date_range);
                if (count($dates) == 2) {
                    $startDate = Carbon::parse($dates[0])->startOfDay();
                    $endDate = Carbon::parse($dates[1])->endOfDay();
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to parse date range: ' . $e->getMessage());
            }
        }

        // Sort by creation date (newest first by default)
        $query->latest();

        $materialOrders = $query->paginate(10)->withQueryString();
        $outlets = Outlet::all();

        return view('pages.material-orders.index', compact('materialOrders', 'outlets'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $rawMaterials = RawMaterial::where('is_active', true)->get();

        // For owner, show all outlets; for others, just their own outlet
        if (Auth::user()->role === 'owner') {
            $outlets = Outlet::all();
        } else {
            $outlets = Outlet::where('id', Auth::user()->outlet_id)->get();
        }

        return view('pages.material-orders.create', compact('rawMaterials', 'outlets'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'franchise_id' => 'required|exists:outlets,id',
            'notes' => 'nullable|string',
            'materials' => 'required|array|min:1',
            'materials.*.raw_material_id' => 'required|exists:raw_materials,id',
            'materials.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $totalAmount = 0;

            // Calculate total amount and validate each item
            foreach ($request->materials as $item) {
                $rawMaterial = RawMaterial::findOrFail($item['raw_material_id']);
                $subtotal = $rawMaterial->price * $item['quantity'];
                $totalAmount += $subtotal;
            }

            // Create material order
            $materialOrder = MaterialOrder::create([
                'franchise_id' => $request->franchise_id,
                'user_id' => Auth::id(),
                'status' => 'pending',
                'total_amount' => $totalAmount,
                'notes' => $request->notes,
            ]);

            // Create order items
            foreach ($request->materials as $item) {
                $rawMaterial = RawMaterial::findOrFail($item['raw_material_id']);
                $subtotal = $rawMaterial->price * $item['quantity'];

                MaterialOrderItem::create([
                    'material_order_id' => $materialOrder->id,
                    'raw_material_id' => $item['raw_material_id'],
                    'quantity' => $item['quantity'],
                    'price_per_unit' => $rawMaterial->price,
                    'subtotal' => $subtotal,
                ]);
            }

            DB::commit();
            return redirect()->route('material-orders.index')
                ->with('success', 'Material order created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to create material order: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(MaterialOrder $materialOrder)
    {
        // Authorize that the user can view this order
        if (Auth::user()->role !== 'owner' && $materialOrder->franchise_id !== Auth::user()->outlet_id) {
            return redirect()->route('material-orders.index')
                ->with('error', 'You do not have permission to view this order');
        }

        $materialOrder->load(['franchise', 'user', 'items.rawMaterial']);

        return view('pages.material-orders.show', compact('materialOrder'));
    }

    /**
     * Update the status of material order
     */
    public function updateStatus(Request $request, MaterialOrder $materialOrder)
    {
        $request->validate([
            'status' => 'required|in:approved,delivered',
        ]);

        // Only owner can approve/deliver orders
        if (Auth::user()->role !== 'owner') {
            return redirect()->back()
                ->with('error', 'You do not have permission to perform this action');
        }

        DB::beginTransaction();
        try {
            $updateData = [
                'status' => $request->status,
            ];

            // Set timestamp for status changes
            if ($request->status === 'approved') {
                $updateData['approved_at'] = now();
            } else if ($request->status === 'delivered') {
                $updateData['delivered_at'] = now();

                // Update stock quantities when order is delivered
                foreach ($materialOrder->items as $item) {
                    $rawMaterial = $item->rawMaterial;
                    // Kurangi stok
                    $rawMaterial->stock -= $item->quantity;

                    // Validasi stok tidak boleh negatif
                    if ($rawMaterial->stock < 0) {
                        DB::rollBack();
                        return redirect()->back()
                            ->with('error', 'Insufficient stock for ' . $rawMaterial->name);
                    }

                    $rawMaterial->save();
                }
            }

            $materialOrder->update($updateData);

            DB::commit();
            return redirect()->route('material-orders.show', $materialOrder)
                ->with('success', 'Order status updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to update order status: ' . $e->getMessage());
        }
    }

    /**
     * Cancel a pending material order
     */
    public function cancel(MaterialOrder $materialOrder)
    {
        // Only pending orders can be cancelled
        if ($materialOrder->status !== 'pending') {
            return redirect()->back()
                ->with('error', 'Only pending orders can be cancelled');
        }

        // Can only cancel own orders if not owner
        if (Auth::user()->role !== 'owner' && $materialOrder->user_id !== Auth::id()) {
            return redirect()->back()
                ->with('error', 'You can only cancel your own orders');
        }

        try {
            $materialOrder->delete();
            return redirect()->route('material-orders.index')
                ->with('success', 'Material order cancelled successfully');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to cancel order: ' . $e->getMessage());
        }
    }
}
