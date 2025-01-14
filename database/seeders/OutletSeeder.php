<?php

namespace Database\Seeders;

use App\Models\Outlet;
use Illuminate\Database\Seeder;

class OutletSeeder extends Seeder
{
    public function run(): void
    {
        Outlet::create([
            'name' => 'Outlet Purwokerto',
            'address' => 'Jl. Utama No. 1',
            'phone' => '085211553430'
        ]);

        Outlet::create([
            'name' => 'Outlet Cilacap',
            'address' => 'Jl. Cabang No. 2',
            'phone' => '085211553430'
        ]);
    }
}
