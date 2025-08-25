<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $storeOwner = User::where('role', 'store')->first();

        if (!$storeOwner) {
            $storeOwner = User::create([
                'name' => 'Store Owner',
                'email' => 'store@hani.com',
                'phone' => '+966501234568',
                'password' => bcrypt('store123'),
                'role' => 'store',
                'is_active' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]);
        }

        $stores = [
            [
                'user_id' => $storeOwner->id,
                'store_name' => 'Al-Rajhi Store',
                'description' => 'Premium retail store offering electronics and home appliances',
                'business_type' => 'Electronics',
                'address' => 'King Fahd Road, Riyadh',
                'city' => 'Riyadh',
                'state' => 'Riyadh Province',
                'country' => 'Saudi Arabia',
                'postal_code' => '12345',
                'latitude' => 24.7136,
                'longitude' => 46.6753,
                'phone' => '+966112345678',
                'email' => 'info@alrajhi.com',
                'website' => 'https://alrajhi.com',
                'business_hours' => json_encode([
                    'monday' => ['09:00', '18:00'],
                    'tuesday' => ['09:00', '18:00'],
                    'wednesday' => ['09:00', '18:00'],
                    'thursday' => ['09:00', '18:00'],
                    'friday' => ['09:00', '18:00'],
                    'saturday' => ['09:00', '18:00'],
                    'sunday' => ['09:00', '18:00'],
                ]),
                'payment_methods' => json_encode(['cash', 'card', 'mobile_payment']),
                'services' => json_encode(['delivery', 'pickup', 'installation']),
                'is_active' => true,
                'is_approved' => true,
                'approved_at' => now(),
            ],
            [
                'user_id' => $storeOwner->id,
                'store_name' => 'Saudi Coffee House',
                'description' => 'Traditional Arabic coffee and modern café',
                'business_type' => 'Food & Beverage',
                'address' => 'Tahlia Street, Jeddah',
                'city' => 'Jeddah',
                'state' => 'Makkah Province',
                'country' => 'Saudi Arabia',
                'postal_code' => '23456',
                'latitude' => 21.5433,
                'longitude' => 39.1679,
                'phone' => '+966122345678',
                'email' => 'info@saudicoffee.com',
                'website' => 'https://saudicoffee.com',
                'business_hours' => json_encode([
                    'monday' => ['08:00', '20:00'],
                    'tuesday' => ['08:00', '20:00'],
                    'wednesday' => ['08:00', '20:00'],
                    'thursday' => ['08:00', '20:00'],
                    'friday' => ['08:00', '20:00'],
                    'saturday' => ['08:00', '20:00'],
                    'sunday' => ['08:00', '20:00'],
                ]),
                'payment_methods' => json_encode(['cash', 'card']),
                'services' => json_encode(['delivery', 'pickup']),
                'is_active' => true,
                'is_approved' => true,
                'approved_at' => now(),
            ],
            [
                'user_id' => $storeOwner->id,
                'store_name' => 'Desert Fashion Mall',
                'description' => 'Luxury fashion and accessories store',
                'business_type' => 'Fashion',
                'address' => 'Olaya Street, Riyadh',
                'city' => 'Riyadh',
                'state' => 'Riyadh Province',
                'country' => 'Saudi Arabia',
                'postal_code' => '34567',
                'latitude' => 24.7136,
                'longitude' => 46.6753,
                'phone' => '+966113456789',
                'email' => 'info@desertfashion.com',
                'website' => 'https://desertfashion.com',
                'business_hours' => json_encode([
                    'monday' => ['10:00', '20:00'],
                    'tuesday' => ['10:00', '20:00'],
                    'wednesday' => ['10:00', '20:00'],
                    'thursday' => ['10:00', '20:00'],
                    'friday' => ['10:00', '20:00'],
                    'saturday' => ['10:00', '20:00'],
                    'sunday' => ['10:00', '20:00'],
                ]),
                'payment_methods' => json_encode(['cash', 'card']),
                'services' => json_encode(['delivery', 'pickup']),
                'is_active' => true,
                'is_approved' => true,
                'approved_at' => now(),
            ],
            [
                'user_id' => $storeOwner->id,
                'store_name' => 'Gulf Electronics',
                'description' => 'Consumer electronics and gadgets store',
                'business_type' => 'Electronics',
                'address' => 'King Abdullah Road, Dammam',
                'city' => 'Dammam',
                'state' => 'Eastern Province',
                'country' => 'Saudi Arabia',
                'postal_code' => '45678',
                'latitude' => 26.4207,
                'longitude' => 50.0888,
                'phone' => '+966133456789',
                'email' => 'info@gulfelectronics.com',
                'website' => 'https://gulfelectronics.com',
                'business_hours' => json_encode([
                    'monday' => ['09:00', '18:00'],
                    'tuesday' => ['09:00', '18:00'],
                    'wednesday' => ['09:00', '18:00'],
                    'thursday' => ['09:00', '18:00'],
                    'friday' => ['09:00', '18:00'],
                    'saturday' => ['09:00', '18:00'],
                    'sunday' => ['09:00', '18:00'],
                ]),
                'payment_methods' => json_encode(['cash', 'card', 'mobile_payment']),
                'services' => json_encode(['delivery', 'pickup', 'installation']),
                'is_active' => true,
                'is_approved' => true,
                'approved_at' => now(),
            ],
            [
                'user_id' => $storeOwner->id,
                'store_name' => 'Arabian Spices Market',
                'description' => 'Traditional spices and herbs store',
                'business_type' => 'Food & Beverage',
                'address' => 'Souq Al-Zal, Riyadh',
                'city' => 'Riyadh',
                'state' => 'Riyadh Province',
                'country' => 'Saudi Arabia',
                'postal_code' => '56789',
                'latitude' => 24.7136,
                'longitude' => 46.6753,
                'phone' => '+966114567890',
                'email' => 'info@arabianspices.com',
                'website' => 'https://arabianspices.com',
                'business_hours' => json_encode([
                    'monday' => ['07:00', '16:00'],
                    'tuesday' => ['07:00', '16:00'],
                    'wednesday' => ['07:00', '16:00'],
                    'thursday' => ['07:00', '16:00'],
                    'friday' => ['07:00', '16:00'],
                    'saturday' => ['07:00', '16:00'],
                    'sunday' => ['07:00', '16:00'],
                ]),
                'payment_methods' => json_encode(['cash', 'card']),
                'services' => json_encode(['delivery', 'pickup']),
                'is_active' => true,
                'is_approved' => true,
                'approved_at' => now(),
            ],
        ];

       
    }
}
