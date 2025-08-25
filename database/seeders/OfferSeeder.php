<?php

namespace Database\Seeders;

use App\Models\Offer;
use App\Models\Store;
use Illuminate\Database\Seeder;

class OfferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stores = Store::all();

        foreach ($stores as $store) {
            // Create 2-4 offers per store
            $offerCount = rand(2, 4);
            
            for ($i = 1; $i <= $offerCount; $i++) {
                $discountType = rand(0, 1) ? 'percentage' : 'fixed';
                $discountValue = $discountType === 'percentage' ? rand(10, 50) : rand(5, 100);
                
                Offer::create([
                    'store_id' => $store->id,
                    'title' => $this->getOfferTitle($store->business_type, $i),
                    'description' => $this->getOfferDescription($store->business_type, $discountType, $discountValue),
                    'discount_type' => $discountType,
                    'discount_value' => $discountValue,
                    'minimum_purchase' => rand(50, 500),
                    'max_usage_per_user' => rand(1, 5),
                    'total_usage_limit' => rand(100, 1000),
                    'current_usage_count' => 0,
                    'valid_from' => now(),
                    'valid_until' => now()->addDays(rand(30, 90)),
                    'terms' => json_encode([
                      
                        'Cannot be combined with other offers',
                        'Subject to availability'
                    ]),
                    'is_active' => true,
                    'is_featured' => rand(0, 1),
                ]);
            }
        }
    }

    private function getOfferTitle($businessType, $index)
    {
        $titles = [
            'Electronics' => [
                'Electronics Sale',
                'Tech Deals',
                'Gadget Discounts',
                'Smart Home Offers'
            ],
            'Food & Beverage' => [
                'Food Specials',
                'Beverage Deals',
                'Café Discounts',
                'Restaurant Offers'
            ],
            'Fashion' => [
                'Fashion Sale',
                'Style Deals',
                'Accessory Discounts',
                'Trendy Offers'
            ]
        ];

        $categoryTitles = $titles[$businessType] ?? ['Special Offer', 'Limited Deal', 'Exclusive Discount', 'Premium Offer'];
        return $categoryTitles[($index - 1) % count($categoryTitles)];
    }

    private function getOfferDescription($businessType, $discountType, $discountValue)
    {
        $discountText = $discountType === 'percentage' 
            ? "Get {$discountValue}% off" 
            : "Save SAR {$discountValue}";

        $descriptions = [
            'Electronics' => "Amazing {$discountText} on all electronics and gadgets. Don't miss out on these incredible deals!",
            'Food & Beverage' => "Enjoy {$discountText} on delicious food and refreshing beverages. Perfect for any occasion!",
            'Fashion' => "Style yourself with {$discountText} on trendy fashion items and accessories. Look fabulous for less!"
        ];

        return $descriptions[$businessType] ?? "Special {$discountText} offer available for a limited time only!";
    }
}
