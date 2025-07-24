<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Outlet;

class OutletSeeder extends Seeder
{
    public function run()
    {
        Outlet::firstOrCreate(['id' => 1], [
            'name' => 'Outlet Pusat',
            // tambahkan field lain yang diperlukan
        ]);
    }
}
