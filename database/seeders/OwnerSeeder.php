<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OwnerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Owner Seblak Sulthane',
            'email' => 'owner@seblaksulthane.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            // 'outlet_id' => 1,

        ]);

        $this->command->info('Owner account created successfully!');
    }
}
