<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\LoyaltyCard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@hani.com',
            'phone' => '+966501234567',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        // Create store owner
        $storeOwner = User::create([
            'name' => 'Store Owner',
            'email' => 'store@hani.com',
            'phone' => '+966501234568',
            'password' => Hash::make('store123'),
            'role' => 'store',
            'is_active' => true,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        // Create regular user
        $user = User::create([
            'name' => 'Regular User',
            'email' => 'user@hani.com',
            'phone' => '+966501234569',
            'password' => Hash::make('user123'),
            'role' => 'client',
            'is_active' => true,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

      
    }
}
