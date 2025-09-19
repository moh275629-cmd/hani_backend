<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\Purchase;
use App\Http\Controllers\LoyaltyCardController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OfferController extends Controller
{
    /**
     * Display a listing of available offers
     */
    public function index(Request $request): JsonResponse
    {
        $query = Offer::with(['store'])
            ->where('is_active', true)
            ->whereHas('store', function($q) {
                $q->where('is_approved', true);
            });

        // Filter by store if provided
        if ($request->has('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        // Filter by discount type if provided
        if ($request->has('discount_type')) {
            $query->where('discount_type', $request->discount_type);
        }

        // Filter by minimum purchase if provided
        if ($request->has('min_purchase')) {
            $query->where('minimum_purchase', '<=', $request->min_purchase);
        }

       if ($request->has('state')) {
    $state = $request->get('state');

    $query->where(function ($q) use ($state) {
        // Offers from stores whose primary state matches (exact only)
        $q->whereHas('store', function ($storeQuery) use ($state) {
            $storeQuery->where(function ($stateQuery) use ($state) {
                $stateQuery->where('state', $state)
                          ->orWhere('state', (string)$state)
                          ->orWhere('state', (int)$state);
            });
        })
        
        // OR offers from stores that have active branches in this state
        ->orWhereHas('store.branches', function ($branchQuery) use ($state) {
            $branchQuery->where('is_active', true)
                       ->where(function ($branchStateQuery) use ($state) {
                           $branchStateQuery->where('wilaya_code', $state)
                                           ->orWhere('wilaya_code', (string)$state)
                                           ->orWhere('wilaya_code', (int)$state);
                       });
        });
    });
}


if ($request->has('city')) {
    $city = $request->get('city');
    \Log::info('Filtering offers by city', ['requested_city' => $city]);
    
    $query->where(function ($q) use ($city) {
        // Offers from stores whose primary city matches (exact only)
        $q->whereHas('store', function ($storeQuery) use ($city) {
            $storeQuery->where('city', $city);
        })
        
        // OR offers from stores that have active branches in this city (exact only)
        ->orWhereHas('store.branches', function ($branchQuery) use ($city) {
            $branchQuery->where('city', $city)
                       ->where('is_active', true);
        });
    });
}


        // Search by title or description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $offers = $query->orderBy('created_at', 'desc')->get();
        
        // Debug: Log the offers being returned
        \Log::info('Offers retrieved', [
            'total_offers' => $offers->count(),
            'offers_sample' => $offers->take(3)->map(function($offer) {
                return [
                    'id' => $offer->id,
                    'title' => $offer->title,
                    'store_id' => $offer->store_id,
                    'store_state' => $offer->store->state ?? 'N/A'
                ];
            })->toArray()
        ]);

        // Add image URLs to offers and clean data
        $offers->transform(function ($offer) {
            try {
                // Ensure blob fields are not included in the response
                $offer->makeHidden(['image_blob']);
                
                // Clean any potentially corrupted data
                $offer->title = $this->cleanString($offer->title);
                $offer->description = $this->cleanString($offer->description);
                
                // Prioritize Cloudinary URLs over blob
                $offer->image_url = $offer->getMainMediaUrl() 
                    ?: ($offer->hasImageBlob() ? url("/api/images/offer/{$offer->id}") : null);
                
                // Add rating information
                $offer->average_rating = $offer->getAverageRating();
                $offer->total_ratings = $offer->getTotalRatings();
                    
                return $offer;
            } catch (\Exception $e) {
                \Log::error('Error processing offer', [
                    'offer_id' => $offer->id,
                    'error' => $e->getMessage()
                ]);
                
                // Return a cleaned version of the offer
                $offer->makeHidden(['image_blob']);
                $offer->title = $this->cleanString($offer->title ?? 'Untitled');
                $offer->description = $this->cleanString($offer->description ?? 'No description');
                $offer->image_url = null;
                
                return $offer;
            }
        });

        return response()->json([
            'message' => 'Offers retrieved successfully',
            'data' => $offers
        ]);
    }

    /**
     * Debug endpoint to check all offers
     */
    public function debug(Request $request): JsonResponse
    {
        $allOffers = Offer::with('store')->get();
        $activeOffers = Offer::where('is_active', true)->get();
        $validOffers = Offer::where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->get();
        $approvedStoreOffers = Offer::where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->whereHas('store', function($q) {
                $q->where('is_approved', true)
                  ->where('is_active', true);
            })
            ->get();

        return response()->json([
            'debug_info' => [
                'total_offers' => $allOffers->count(),
                'active_offers' => $activeOffers->count(),
                'valid_offers' => $validOffers->count(),
                'approved_store_offers' => $approvedStoreOffers->count(),
                'all_offers' => $allOffers->map(function($offer) {
                    return [
                        'id' => $offer->id,
                        'title' => $offer->title,
                        'store_id' => $offer->store_id,
                        'store_name' => $offer->store->store_name ?? 'N/A',
                        'store_approved' => $offer->store->is_approved ?? false,
                        'store_active' => $offer->store->is_active ?? false,
                        'is_active' => $offer->is_active,
                        'valid_from' => $offer->valid_from,
                        'valid_until' => $offer->valid_until,
                        'is_valid_now' => $offer->valid_from <= now() && $offer->valid_until >= now()
                    ];
                })
            ]
        ]);
    }

    /**
     * Display the specified offer
     */
   

public function show($offerId): JsonResponse
{
    $offer = Offer::find($offerId);

    if (!$offer || !$offer->is_active || $offer->isExpired()) {
        return response()->json([
            'message' => 'Offer not available',
            'data' => null
        ], 404);
    }

    $offer->load(['store']);

    // Add image URL to offer - prioritize Cloudinary URLs over blob
    $offer->image_url = $offer->getMainMediaUrl() 
        ?: ($offer->hasImageBlob() ? url("/api/images/offer/{$offer->id}") : null);

    // Add rating information
    $offer->average_rating = $offer->getAverageRating();
    $offer->total_ratings = $offer->getTotalRatings();

    // Count how many times the offer was redeemed
    $purchasesCount = Purchase::where('redeemed_offer_id', $offer->id)->count();

    return response()->json([
        'message' => 'Offer retrieved successfully',
        'data' => [
            'offer' => $offer,
            'store' => $offer->store,
            'purchases_countt' => $purchasesCount
        ]
    ]);
}

    
    

    /**
     * Redeem an offer
     */
    public function redeem(Request $request, Offer $offer): JsonResponse
    {
        $user = Auth::user();

        // Validate request
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if offer is available
        if (!$offer->is_active || $offer->isExpired()) {
            return response()->json([
                'message' => 'Offer is not available',
                'data' => null
            ], 400);
        }

        // Check minimum purchase requirement
        if ($request->amount < $offer->minimum_purchase) {
            return response()->json([
                'message' => "Minimum purchase amount is $" . $offer->minimum_purchase,
                'data' => null
            ], 400);
        }

        // Check usage limits
        $userUsage = Purchase::where('user_id', $user->id)
            ->where('offer_id', $offer->id)
            ->count();

        if ($userUsage >= $offer->max_usage_per_user) {
            return response()->json([
                'message' => 'You have reached the maximum usage limit for this offer',
                'data' => null
            ], 400);
        }

        // Check total usage limit
        if ($offer->total_usage_limit) {
            $totalUsage = Purchase::where('offer_id', $offer->id)->count();
            if ($totalUsage >= $offer->total_usage_limit) {
                return response()->json([
                    'message' => 'This offer has reached its total usage limit',
                    'data' => null
                ], 400);
            }
        }

        // Calculate discount
        $discountAmount = $this->calculateDiscount($offer, $request->amount);

        // Create purchase record
        $purchase = Purchase::create([
            'user_id' => $user->id,
            'branch_id' => $request->branch_id ?? null,
            'offer_id' => $offer->id,
            'transaction_id' => $this->generateTransactionId(),
            'amount' => $request->amount,
            'discount_amount' => $discountAmount,
            'final_amount' => $request->amount - $discountAmount,
            'quantity' => $request->quantity,
            'product_name' => $request->product_name ?? 'General Purchase',
            'details' => $request->details ?? null,
            'status' => 'completed',
            'purchase_date' => now(),
        ]);

        // Add points to loyalty card if applicable
        if ($request->amount > 0) {
            $points = $this->calculatePoints($request->amount);
            app(LoyaltyCardController::class)->addPoints($points, $request->amount);
        }

        return response()->json([
            'message' => 'Offer redeemed successfully',
            'data' => [
                'purchase' => $purchase,
                'discount_amount' => $discountAmount,
                'final_amount' => $request->amount - $discountAmount,
                'points_earned' => $points ?? 0
            ]
        ], 201);
    }

    /**
     * Get user's redeemed offers
     */
    public function redeemed(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $redeemedOffers = Purchase::where('user_id', $user->id)
            ->whereNotNull('offer_id')
            ->with(['offer', 'branch'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Redeemed offers retrieved successfully',
            'data' => $redeemedOffers
        ]);
    }

    /**
     * Calculate discount amount based on offer type
     */
    private function calculateDiscount(Offer $offer, float $amount): float
    {
        switch ($offer->discount_type) {
            case 'percentage':
                return ($amount * $offer->discount_value) / 100;
            case 'fixed':
                return min($offer->discount_value, $amount);
            case 'free_shipping':
                return 0; // Free shipping is handled separately
            default:
                return 0;
        }
    }

    /**
     * Calculate points based on purchase amount
     */
    private function calculatePoints(float $amount): int
    {
        // 1 point per dollar spent
        return (int) $amount;
    }

    /**
     * Generate unique transaction ID
     */
    private function generateTransactionId(): string
    {
        return 'TXN' . time() . rand(1000, 9999);
    }

    /**
     * Clean string to prevent UTF-8 encoding issues
     */
    private function cleanString($string)
    {
        if (is_null($string)) {
            return null;
        }
        
        // Remove any invalid UTF-8 sequences
        $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        
        // Remove any control characters that might cause issues
        $string = preg_replace('/[\x00-\x1F\x7F]/', '', $string);
        
        return $string;
    }

    /**
     * Clean up corrupted data in offers table
     */
    public function cleanupCorruptedData()
    {
        try {
            $corruptedOffers = Offer::whereRaw('LENGTH(title) > 0 AND title NOT REGEXP "^[[:print:]]*$"')
                ->orWhereRaw('LENGTH(description) > 0 AND description NOT REGEXP "^[[:print:]]*$"')
                ->get();

            $cleanedCount = 0;
            foreach ($corruptedOffers as $offer) {
                $offer->title = $this->cleanString($offer->title);
                $offer->description = $this->cleanString($offer->description);
                $offer->save();
                $cleanedCount++;
            }

            return response()->json([
                'message' => 'Data cleanup completed',
                'cleaned_offers' => $cleanedCount
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error during cleanup',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
