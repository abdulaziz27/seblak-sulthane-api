<?php

namespace Database\Seeders;

use App\Models\MaterialOrder;
use App\Models\RawMaterial;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RecalculateReservedStockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset semua reserved_stock ke 0
        DB::table('raw_materials')->update(['reserved_stock' => 0]);

        // Ambil semua pesanan dengan status 'pending' dan 'approved'
        $pendingOrders = MaterialOrder::where('status', 'pending')
            ->orWhere('status', 'approved')
            ->with('items.rawMaterial')
            ->get();

        // Hitung reserved_stock untuk setiap bahan baku
        $reservedStocks = [];

        foreach ($pendingOrders as $order) {
            foreach ($order->items as $item) {
                $materialId = $item->raw_material_id;
                if (!isset($reservedStocks[$materialId])) {
                    $reservedStocks[$materialId] = 0;
                }
                $reservedStocks[$materialId] += $item->quantity;
            }
        }

        // Update each raw material with reserved stock
        foreach ($reservedStocks as $materialId => $reservedQuantity) {
            RawMaterial::where('id', $materialId)->update([
                'reserved_stock' => $reservedQuantity
            ]);
        }

        $this->command->info('Reserved stock successfully recalculated for ' . count($reservedStocks) . ' materials.');
    }
}
