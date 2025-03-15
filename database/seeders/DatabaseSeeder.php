<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Outlet;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create Owner account (not affiliated with any outlet)
        User::create([
            'name' => 'Owner Seblak Sulthane',
            'email' => 'owner@seblaksulthane.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
        ]);

        // Create outlets first
        $outletPurwokerto = Outlet::create([
            'name' => 'Outlet Purwokerto',
            'address1' => 'Jl. HR. Bunyamin No. 10, Purwokerto',
            'phone' => '085211553430'
        ]);

        $outletCilacap = Outlet::create([
            'name' => 'Outlet Cilacap',
            'address1' => 'Jl. Gatot Subroto No. 15, Cilacap',
            'phone' => '085211553431'
        ]);

        // Create admin accounts for each outlet
        User::create([
            'name' => 'Admin Purwokerto',
            'email' => 'adminpurwokerto@seblaksulthane.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'outlet_id' => $outletPurwokerto->id
        ]);

        User::create([
            'name' => 'Admin Cilacap',
            'email' => 'admincilacap@seblaksulthane.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'outlet_id' => $outletCilacap->id
        ]);

        // Create staff accounts for each outlet
        User::create([
            'name' => 'Staff Purwokerto',
            'email' => 'staffpurwokerto@seblaksulthane.com',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'outlet_id' => $outletPurwokerto->id
        ]);

        User::create([
            'name' => 'Staff Cilacap',
            'email' => 'staffcilacap@seblaksulthane.com',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'outlet_id' => $outletCilacap->id
        ]);

        // Run other seeders
        $this->call([
            CategorySeeder::class,
            ProductSeeder::class,
            DiscountSeeder::class,
            OwnerSeeder::class,
        ]);
    }
}
