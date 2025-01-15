<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DiscountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //manual input
        \App\Models\Discount::create([
            'name' => 'Member Discount',
            'description' => 'Diskon untuk member',
            'type' => 'percentage',
            'value' => 15,
            'category' => 'member',
            'status' => 'active'
        ]);

        \App\Models\Discount::create([
            'name' => 'Grand Opening Cabang',
            'description' => 'Diskon grand opening outlet',
            'type' => 'percentage',
            'value' => 5,
            'category' => 'event',
            'status' => 'active'
        ]);

        // \App\Models\Discount::create([
        //     'name' => 'Black Friday',
        //     'description' => 'Discount Black Friday',
        //     'type' => 'percentage',
        //     'value' => 15,
        //     'status' => 'active',
        //     'expired_date' => '2025-12-31'
        // ]);
    }
}
