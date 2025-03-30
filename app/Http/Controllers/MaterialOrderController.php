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

        // Filter by outlet based on role and warehouse status
        $user = Auth::user();
        $isWarehouseStaff = $user->isWarehouseStaff();

        // If user is owner or a warehouse staff, they can see all orders
        if ($user->role !== 'owner' && !$isWarehouseStaff) {
            // Regular staff/admin only see their outlet's orders
            $query->where('franchise_id', $user->outlet_id);
        } elseif ($request->franchise_id) {
            // Filter by selected outlet if a filter is applied (for owner/warehouse staff)
            $query->where('franchise_id', $request->franchise_id);
        }

        // Filter by status
        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        // Filter by payment method
        if ($request->has('payment_method') && $request->payment_method != '') {
            $query->where('payment_method', $request->payment_method);
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

        $materialOrders = $query->paginate(15)->withQueryString();
        $outlets = Outlet::all();

        // Pass warehouse status flag to view for conditional rendering
        $isWarehouse = $isWarehouseStaff || $user->role === 'owner';

        return view('pages.material-orders.index', compact('materialOrders', 'outlets', 'isWarehouse'));
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

        // Payment method options
        $paymentMethods = [
            'cash' => 'Tunai',
            'bank_transfer' => 'Bank Transfer',
            'e-wallet' => 'E-Wallet'
        ];

        return view('pages.material-orders.create', compact('rawMaterials', 'outlets', 'paymentMethods'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'franchise_id' => 'required|exists:outlets,id',
            'payment_method' => 'required|in:cash,bank_transfer,e-wallet',
            'notes' => 'nullable|string',
            'materials' => 'required|array|min:1',
            'materials.*.raw_material_id' => 'required|exists:raw_materials,id',
            'materials.*.quantity' => 'required|integer|min:1',
        ], [
            'franchise_id.required' => 'Outlet harus dipilih',
            'payment_method.required' => 'Metode pembayaran harus dipilih',
            'materials.required' => 'Minimal satu bahan baku harus dipilih',
            'materials.min' => 'Minimal satu bahan baku harus dipilih',
            'materials.*.quantity.min' => 'Jumlah bahan baku harus minimal 1'
        ]);

        DB::beginTransaction();
        try {
            $totalAmount = 0;
            $errorMessages = [];

            // Validasi stok tersedia untuk setiap bahan baku yang dipesan
            foreach ($request->materials as $index => $item) {
                $rawMaterial = RawMaterial::findOrFail($item['raw_material_id']);

                // Cek apakah jumlah yang diminta melebihi stok yang tersedia
                if ($item['quantity'] > $rawMaterial->available_stock) {
                    $errorMessages[] = "Stok tidak cukup untuk <strong>{$rawMaterial->name}</strong>. Tersedia: {$rawMaterial->available_stock} {$rawMaterial->unit}, diminta: {$item['quantity']} {$rawMaterial->unit}";
                } else {
                    $subtotal = $rawMaterial->price * $item['quantity'];
                    $totalAmount += $subtotal;
                }
            }

            // Jika ada error stok, tampilkan semua pesan error
            if (count($errorMessages) > 0) {
                DB::rollBack();
                return redirect()->back()
                    ->with('error', '<strong>Pemesanan Gagal!</strong><br>' . implode('<br>', $errorMessages))
                    ->withInput();
            }

            // Create material order
            $materialOrder = MaterialOrder::create([
                'franchise_id' => $request->franchise_id,
                'user_id' => Auth::id(),
                'status' => 'pending',
                'payment_method' => $request->payment_method,
                'total_amount' => $totalAmount,
                'notes' => $request->notes,
            ]);

            // Create order items dan reservasi stok
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

                // Reservasi stok
                $rawMaterial->reserved_stock += $item['quantity'];
                $rawMaterial->save();
            }

            DB::commit();
            return redirect()->route('material-orders.index')
                ->with('success', 'Pesanan bahan baku berhasil dibuat');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Gagal membuat pesanan bahan baku: ' . $e->getMessage())
                ->withInput();
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(MaterialOrder $materialOrder)
    {
        $user = Auth::user();
        $isWarehouseStaff = $user->isWarehouseStaff();

        // Authorize that the user can view this order
        if ($user->role !== 'owner' && !$isWarehouseStaff && $materialOrder->franchise_id !== $user->outlet_id) {
            return redirect()->route('material-orders.index')
                ->with('error', 'Anda tidak memiliki izin untuk melihat pesanan ini');
        }

        $materialOrder->load(['franchise', 'user', 'items.rawMaterial']);

        // Pass warehouse status to view for conditional rendering of approval buttons
        $isWarehouse = $isWarehouseStaff || $user->role === 'owner';

        return view('pages.material-orders.show', compact('materialOrder', 'isWarehouse'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MaterialOrder $materialOrder)
    {
        $user = Auth::user();
        $isWarehouseStaff = $user->isWarehouseStaff();

        // Authorize that the user can edit this order
        if ($user->role !== 'owner' && !$isWarehouseStaff && $materialOrder->franchise_id !== $user->outlet_id) {
            return redirect()->route('material-orders.index')
                ->with('error', 'Anda tidak memiliki izin untuk mengubah pesanan ini');
        }

        // Only pending orders can be edited
        if ($materialOrder->status !== 'pending') {
            return redirect()->route('material-orders.show', $materialOrder)
                ->with('error', 'Hanya pesanan dengan status pending yang dapat diubah');
        }

        $rawMaterials = RawMaterial::where('is_active', true)->get();

        // For owner/warehouse users, show all outlets; for others, just their own outlet
        if ($user->role === 'owner' || $isWarehouseStaff) {
            $outlets = Outlet::all();
        } else {
            $outlets = Outlet::where('id', $user->outlet_id)->get();
        }

        // Payment method options
        $paymentMethods = [
            'cash' => 'Tunai',
            'bank_transfer' => 'Bank Transfer',
            'e-wallet' => 'E-Wallet'
        ];

        $materialOrder->load(['items.rawMaterial', 'franchise', 'user']);

        return view('pages.material-orders.edit', compact('materialOrder', 'rawMaterials', 'outlets', 'paymentMethods'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MaterialOrder $materialOrder)
    {
        $user = Auth::user();
        $isWarehouseStaff = $user->isWarehouseStaff();

        // Authorize that the user can update this order
        if ($user->role !== 'owner' && !$isWarehouseStaff && $materialOrder->franchise_id !== $user->outlet_id) {
            return redirect()->route('material-orders.index')
                ->with('error', 'Anda tidak memiliki izin untuk mengubah pesanan ini');
        }

        // Only pending orders can be updated
        if ($materialOrder->status !== 'pending') {
            return redirect()->route('material-orders.show', $materialOrder)
                ->with('error', 'Hanya pesanan dengan status pending yang dapat diubah');
        }

        $request->validate([
            'franchise_id' => 'required|exists:outlets,id',
            'payment_method' => 'required|in:cash,bank_transfer,e-wallet',
            'notes' => 'nullable|string',
            'materials' => 'required|array|min:1',
            'materials.*.raw_material_id' => 'required|exists:raw_materials,id',
            'materials.*.quantity' => 'required|integer|min:1',
        ], [
            'franchise_id.required' => 'Outlet harus dipilih',
            'payment_method.required' => 'Metode pembayaran harus dipilih',
            'materials.required' => 'Minimal satu bahan baku harus dipilih',
            'materials.min' => 'Minimal satu bahan baku harus dipilih',
            'materials.*.quantity.min' => 'Jumlah bahan baku harus minimal 1'
        ]);

        DB::beginTransaction();
        try {
            $totalAmount = 0;
            $errorMessages = [];

            // Simpan informasi item lama untuk bisa membatalkan reservasi
            $originalItems = collect($materialOrder->items->toArray());

            // Informasi item baru
            $newItems = collect($request->materials);

            // Validasi stok tersedia untuk setiap bahan baku yang dipesan
            foreach ($newItems as $item) {
                $rawMaterial = RawMaterial::findOrFail($item['raw_material_id']);

                // Cari item yang sama di pesanan asli
                $originalItem = $originalItems->firstWhere('raw_material_id', $item['raw_material_id']);
                $originalQty = $originalItem ? $originalItem['quantity'] : 0;

                // Hitung selisih kuantitas
                $qtyDifference = $item['quantity'] - $originalQty;

                // Jika ada penambahan kuantitas, periksa stok tersedia
                if ($qtyDifference > 0) {
                    // Cek apakah penambahan melebihi stok yang tersedia
                    if ($qtyDifference > $rawMaterial->available_stock) {
                        $errorMessages[] = "Stok tidak cukup untuk <strong>{$rawMaterial->name}</strong>. Tersedia: {$rawMaterial->available_stock} {$rawMaterial->unit}, tambahan diminta: {$qtyDifference} {$rawMaterial->unit}";
                    }
                }

                $subtotal = $rawMaterial->price * $item['quantity'];
                $totalAmount += $subtotal;
            }

            // Jika ada error stok, tampilkan semua pesan error
            if (count($errorMessages) > 0) {
                DB::rollBack();
                return redirect()->back()
                    ->with('error', '<strong>Pembaruan Pesanan Gagal!</strong><br>' . implode('<br>', $errorMessages))
                    ->withInput();
            }

            // If user is warehouse staff and not the original outlet's staff, preserve original franchise_id
            $franchiseId = $request->franchise_id;
            if ($isWarehouseStaff && $user->outlet_id !== $materialOrder->franchise_id && $user->role !== 'owner') {
                $franchiseId = $materialOrder->franchise_id;
            }

            // Update material order
            $materialOrder->update([
                'franchise_id' => $franchiseId,
                'payment_method' => $request->payment_method,
                'total_amount' => $totalAmount,
                'notes' => $request->notes,
            ]);

            // Batalkan reservasi stok untuk semua item lama
            foreach ($originalItems as $item) {
                $rawMaterial = RawMaterial::find($item['raw_material_id']);
                if ($rawMaterial) {
                    $rawMaterial->reserved_stock -= $item['quantity'];
                    $rawMaterial->save();
                }
            }

            // Delete existing order items
            $materialOrder->items()->delete();

            // Create new order items dan reservasi stok baru
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

                // Reservasi stok baru
                $rawMaterial->reserved_stock += $item['quantity'];
                $rawMaterial->save();
            }

            DB::commit();
            return redirect()->route('material-orders.show', $materialOrder)
                ->with('success', 'Pesanan bahan baku berhasil diperbarui');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Gagal memperbarui pesanan bahan baku: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Update the status of material order
     */
    public function updateStatus(Request $request, MaterialOrder $materialOrder)
    {
        $user = Auth::user();
        $isWarehouseStaff = $user->isWarehouseStaff();

        // Only owner or warehouse staff can approve/deliver orders
        if ($user->role !== 'owner' && !$isWarehouseStaff) {
            return redirect()->route('material-orders.index')
                ->with('error', 'Anda tidak memiliki izin untuk mengubah status pesanan');
        }

        $request->validate([
            'status' => 'required|in:approved,delivered',
        ]);

        DB::beginTransaction();
        try {
            $updateData = [
                'status' => $request->status,
            ];

            // Set timestamp for status changes
            if ($request->status === 'approved') {
                $updateData['approved_at'] = now();
                // Tidak ada perubahan stok saat approved, hanya status yang berubah
            } else if ($request->status === 'delivered') {
                $updateData['delivered_at'] = now();

                // Saat delivered, kurangi stok aktual dan lepaskan reservasi
                foreach ($materialOrder->items as $item) {
                    $rawMaterial = $item->rawMaterial;

                    // Kurangi stok aktual
                    $rawMaterial->stock -= $item->quantity;

                    // Lepaskan reservasi
                    $rawMaterial->reserved_stock -= $item->quantity;

                    // Validasi stok tidak boleh negatif
                    if ($rawMaterial->stock < 0) {
                        DB::rollBack();
                        return redirect()->back()
                            ->with('error', 'Stok ' . $rawMaterial->name . ' tidak boleh negatif');
                    }

                    $rawMaterial->save();
                }
            }

            $materialOrder->update($updateData);

            DB::commit();
            return redirect()->route('material-orders.show', $materialOrder)
                ->with('success', 'Order status berhasil diupdate');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Gagal melakukan update order status: ' . $e->getMessage());
        }
    }

    /**
     * Cancel a pending material order
     */
    public function cancel(MaterialOrder $materialOrder)
    {
        $user = Auth::user();
        $isWarehouseStaff = $user->isWarehouseStaff();

        // Only pending orders can be cancelled
        if ($materialOrder->status !== 'pending') {
            return redirect()->back()
                ->with('error', 'Hanya pesanan dengan status pending yang dapat dibatalkan');
        }

        // Can only cancel own orders unless owner/warehouse staff
        if ($user->role !== 'owner' && !$isWarehouseStaff && $materialOrder->user_id !== $user->id) {
            return redirect()->back()
                ->with('error', 'Anda hanya dapat membatalkan pesanan yang Anda buat');
        }

        DB::beginTransaction();
        try {
            // Lepaskan reservasi stok untuk semua item
            foreach ($materialOrder->items as $item) {
                $rawMaterial = $item->rawMaterial;
                if ($rawMaterial) {
                    $rawMaterial->reserved_stock -= $item->quantity;
                    $rawMaterial->save();
                }
            }

            // Hapus pesanan
            $materialOrder->delete();

            DB::commit();
            return redirect()->route('material-orders.index')
                ->with('success', 'Pesanan bahan baku berhasil dibatalkan');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Gagal membatalkan pesanan: ' . $e->getMessage());
        }
    }

    /**
     * Soft delete all raw materials
     */
    public function deleteAll()
    {
        try {
            DB::beginTransaction();

            // Log start of operation
            \Log::info('Memulai proses deleteAll untuk bahan baku');

            // Check if any materials are being used in orders
            $materialsInUse = RawMaterial::whereHas('materialOrderItems')->get();

            if ($materialsInUse->isNotEmpty()) {
                // Prepare detailed information about materials in use
                $materialInfo = $materialsInUse->map(function ($material) {
                    return "{$material->name} (ID: {$material->id})";
                })->join(', ');

                // Roll back and return message
                DB::rollBack();
                return redirect()->route('raw-materials.index')
                    ->with('warning', "Tidak dapat menghapus semua bahan baku. Bahan baku berikut masih digunakan dalam pesanan: {$materialInfo}");
            }

            // Track how many materials will be deleted
            $materialCount = RawMaterial::count();

            if ($materialCount === 0) {
                DB::rollBack();
                return redirect()->route('raw-materials.index')
                    ->with('info', 'Tidak ada bahan baku yang ditemukan untuk dihapus.');
            }

            // Soft delete all materials (the SoftDeletes trait will make this a soft delete)
            RawMaterial::query()->delete();

            // Commit the transaction
            DB::commit();

            // Log successful deletion
            \Log::info("Berhasil soft delete semua {$materialCount} bahan baku");

            return redirect()->route('raw-materials.index')
                ->with('success', "Semua {$materialCount} bahan baku berhasil dihapus.");
        } catch (\Exception $e) {
            // Log error
            \Log::error('Error dalam deleteAll bahan baku: ' . $e->getMessage());

            // Rollback transaction if still active
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return redirect()->route('raw-materials.index')
                ->with('error', 'Kesalahan menghapus bahan baku: ' . $e->getMessage());
        }
    }
}
