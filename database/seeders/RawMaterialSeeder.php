<?php

namespace Database\Seeders;

use App\Models\RawMaterial;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RawMaterialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Sample raw materials for a food business (specifically for Seblak)
        $materials = [
            [
                'name' => 'Kerupuk',
                'unit' => 'kg',
                'price' => 25000,
                'stock' => 50,
                'description' => 'Kerupuk untuk bahan dasar seblak',
                'is_active' => true
            ],
            [
                'name' => 'Cabai Merah',
                'unit' => 'kg',
                'price' => 40000,
                'stock' => 10,
                'description' => 'Cabai merah segar',
                'is_active' => true
            ],
            [
                'name' => 'Bawang Putih',
                'unit' => 'kg',
                'price' => 30000,
                'stock' => 15,
                'description' => 'Bawang putih untuk bumbu',
                'is_active' => true
            ],
            [
                'name' => 'Kencur',
                'unit' => 'kg',
                'price' => 45000,
                'stock' => 5,
                'description' => 'Kencur untuk bumbu',
                'is_active' => true
            ],
            [
                'name' => 'Telur',
                'unit' => 'kg',
                'price' => 28000,
                'stock' => 30,
                'description' => 'Telur ayam',
                'is_active' => true
            ],
            [
                'name' => 'Mie',
                'unit' => 'kg',
                'price' => 15000,
                'stock' => 40,
                'description' => 'Mie kering',
                'is_active' => true
            ],
            [
                'name' => 'Sosis',
                'unit' => 'kg',
                'price' => 60000,
                'stock' => 20,
                'description' => 'Sosis untuk topping',
                'is_active' => true
            ],
            [
                'name' => 'Bakso',
                'unit' => 'kg',
                'price' => 70000,
                'stock' => 20,
                'description' => 'Bakso untuk topping',
                'is_active' => true
            ],
            [
                'name' => 'Makaroni',
                'unit' => 'kg',
                'price' => 20000,
                'stock' => 25,
                'description' => 'Makaroni untuk bahan',
                'is_active' => true
            ],
            [
                'name' => 'Ceker Ayam',
                'unit' => 'kg',
                'price' => 45000,
                'stock' => 15,
                'description' => 'Ceker ayam untuk topping',
                'is_active' => true
            ],
            [
                'name' => 'Garam',
                'unit' => 'kg',
                'price' => 12000,
                'stock' => 20,
                'description' => 'Garam untuk bumbu',
                'is_active' => true
            ],
            [
                'name' => 'Gula',
                'unit' => 'kg',
                'price' => 16000,
                'stock' => 25,
                'description' => 'Gula untuk bumbu',
                'is_active' => true
            ],
            [
                'name' => 'Minyak Goreng',
                'unit' => 'liter',
                'price' => 22000,
                'stock' => 50,
                'description' => 'Minyak goreng untuk memasak',
                'is_active' => true
            ],
            [
                'name' => 'Sayur Sawi',
                'unit' => 'kg',
                'price' => 15000,
                'stock' => 10,
                'description' => 'Sayur sawi segar',
                'is_active' => true
            ],
            [
                'name' => 'Siomay',
                'unit' => 'kg',
                'price' => 60000,
                'stock' => 15,
                'description' => 'Siomay untuk topping',
                'is_active' => true
            ],
        ];

        // Insert all raw materials
        foreach ($materials as $material) {
            RawMaterial::create($material);
        }
    }
}
