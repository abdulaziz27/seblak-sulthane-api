<?php

namespace Database\Seeders;

use App\Models\MaterialOrder;
use App\Models\MaterialOrderItem;
use App\Models\RawMaterial;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class MaterialOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Only run if there are outlets, users and raw materials
        if (Outlet::count() == 0 || User::count() == 0 || RawMaterial::count() == 0) {
            return;
        }

        // Get one owner user
        $owner = User::where('role', 'owner')->first();

        // Get outlets
        $outlets = Outlet::all();

        // Get raw materials
        $rawMaterials = RawMaterial::all();

        // Create 5 material orders with different statuses
        $statuses = ['pending', 'approved', 'delivered'];
        $dates = [
            Carbon::now()->subDays(10),
            Carbon::now()->subDays(7),
            Carbon::now()->subDays(5),
            Carbon::now()->subDays(3),
            Carbon::now()->subDays(1),
        ];

        foreach ($outlets as $outlet) {
            foreach ($dates as $index => $date) {
                // Calculate a random status based on the date (older orders more likely to be delivered)
                $status = $statuses[min(2, rand(0, $index))];

                // Create the material order
                $materialOrder = MaterialOrder::create([
                    'franchise_id' => $outlet->id,
                    'user_id' => $owner->id,
                    'status' => $status,
                    'total_amount' => 0, // Will calculate based on items
                    'notes' => 'Pesanan bahan baku untuk ' . $outlet->name,
                    'approved_at' => $status != 'pending' ? $date->copy()->addHours(2) : null,
                    'delivered_at' => $status == 'delivered' ? $date->copy()->addHours(8) : null,
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);

                // Create 3-5 items for each order
                $totalAmount = 0;
                $numItems = rand(3, 5);
                $selectedMaterials = $rawMaterials->random($numItems);

                foreach ($selectedMaterials as $material) {
                    $quantity = rand(1, 10);
                    $subtotal = $material->price * $quantity;
                    $totalAmount += $subtotal;

                    MaterialOrderItem::create([
                        'material_order_id' => $materialOrder->id,
                        'raw_material_id' => $material->id,
                        'quantity' => $quantity,
                        'price_per_unit' => $material->price,
                        'subtotal' => $subtotal,
                        'created_at' => $date,
                        'updated_at' => $date,
                    ]);
                }

                // Update the total amount
                $materialOrder->update(['total_amount' => $totalAmount]);
            }
        }
    }
}
