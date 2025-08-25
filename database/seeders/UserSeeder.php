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

        // Create loyalty card for regular user
        LoyaltyCard::create([
            'user_id' => $user->id,
            'store_id' => 1, // Assuming store with ID 1 exists
            'card_number' => 'HANI' . str_pad($user->id, 8, '0', STR_PAD_LEFT),
            'qr_code' => 'QR' . str_pad($user->id, 8, '0', STR_PAD_LEFT),
        ]);

        // Create additional test users
        User::factory(20)->create()->each(function ($user) {
            if ($user->role === 'client') {
                LoyaltyCard::create([
                    'user_id' => $user->id,
                    'store_id' => 1, // Assuming store with ID 1 exists
                    'card_number' => 'HANI' . str_pad($user->id, 8, '0', STR_PAD_LEFT),
                    'qr_code' => 'QR' . str_pad($user->id, 8, '0', STR_PAD_LEFT),
                ]);
            }
        });
    }
}
