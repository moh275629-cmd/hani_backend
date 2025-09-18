<?php

namespace App\Http\Controllers;

use App\Models\Store;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\Purchase;
use Illuminate\Support\Facades\Auth;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\StoreRejectedMail;

class StoreController extends Controller
{
    /**
     * Display a listing of stores
     */
    public function index(Request $request): JsonResponse
    {
       try{

        $query = Store::where('is_approved', true);
        $query->with('user');
        // Filter by category if provided
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        

        if ($request->has('state')) {
            $query->where('state', 'like', '%' . $request->state . '%');
        }
        if ($request->has('payment_methods')) {
            $query->where('payment_methods', 'like', '%' . $request->payment_methods . '%');
        }
        if ($request->has('services')) {
            $query->where('services', 'like', '%' . $request->services . '%');
        }
        if ($request->has('business_type')) {
            $query->where('business_type', 'like', '%' . $request->business_type . '%');
        }
        // Filter by location if provided
        if ($request->has('city')) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        // Search by name or description
        if ($request->has('search')) {
            $search = $request->search;
            $query->searchByEncryptedFields($search);
        }



        $stores = $query->get();


        return response()->json([
            'message' => 'Stores retrieved successfully',
            'data' => $stores
        ]);
       }catch(\Exception $e){
        return response()->json([
            'message' => 'Error loading stores' . $e->getMessage(),
            'error' => $e->getMessage(),
            'data' => null
        ], 500);
       }
    }

    /**
     * Display the specified store
     */
    public function reject(Request $request, $storeId): JsonResponse
    {
        try{
            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:255',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            $store = Store::find($storeId);
            $user = User::find($store->user_id);
            $email = $user->email;
               
            $user->delete();
            Mail::to($email)->send(new \App\Mail\StoreRejectedMail($user, $store, $request->reason));
           
             return response()->json([
                'message' => 'Store rejected successfully',
                'data' => $store
            ]);
        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error rejecting store' . $e->getMessage(),
                'error' => $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Display the specified store
     */
    public function show($storeId): JsonResponse
    {
        \Log::info('StoreController::show - Store ID: ' . $storeId);

        $store = Store::with('user')->find($storeId);
        \Log::info('StoreController::show - Store: ' . ($store ? $store->id : 'no store'));
        if (!$store) {
            return response()->json([
                'message' => 'Store not found with id: ' . $storeId,
                'data' => null
            ], 404);
        } // ✅ close the if-block here
        
        $store->load([ 'offers' => function($query) {
            $query->where('is_active', true)
                  ->where('valid_from', '<=', now())
                  ->where('valid_until', '>=', now());
        }]);

        // Add image URLs to store
        $store->logo = $store->hasLogoBlob() 
            ? url("/api/images/store/{$store->id}/logo")
            : null;
        $store->banner_url = $store->hasBannerBlob() 
            ? url("/api/images/store/{$store->id}/banner")
            : null;

        // Add image URLs to offers
        $store->offers->transform(function ($offer) {
            // Prioritize Cloudinary URLs over blob
            $offer->image_url = $offer->getMainMediaUrl() 
                ?: ($offer->hasImageBlob() ? url("/api/images/offer/{$offer->id}") : null);
            return $offer;
        });

        return response()->json([
            'message' => 'Store retrieved successfully',
            'data' => $store,
            'success' => true
        ]);
    }

    /**
     * Display the authenticated store's store record
     */
    public function StoreAuthed(): JsonResponse
    {
        $user = Auth::user();
        
        // Fetch store safely without dereferencing null
        $store = Store::where('user_id', $user->id)->first();
        if (!$store) {
            return response()->json([
                'message' => 'Store not found for this user',
                'data' => null
            ], 404);
        }

        $store_id = $store->id;
        \Log::info('StoreController::showStore - Store ID: ' . $store_id);

        $store = Store::find($store_id);
        \Log::info('StoreController::showStore - Store: ' . ($store ? $store->id : 'no store'));
        if (!$store) {
            return response()->json([
                'message' => 'Store not found',
                'data' => null
            ], 404);
        } // ✅ close the if-block here
        
        $store->load([ 'offers' => function($query) {
            $query->where('is_active', true)
                  ->where('valid_from', '<=', now())
                  ->where('valid_until', '>=', now());
        }]);

        // Add image URLs to store
        $store->logo = $store->hasLogoBlob() 
            ? url("/api/images/store/{$store->id}/logo")
            : null;
        $store->banner_url = $store->hasBannerBlob() 
            ? url("/api/images/store/{$store->id}/banner")
            : null;

        // Add image URLs to offers
        $store->offers->transform(function ($offer) {
            // Prioritize Cloudinary URLs over blob
            $offer->image_url = $offer->getMainMediaUrl() 
                ?: ($offer->hasImageBlob() ? url("/api/images/offer/{$offer->id}") : null);
            return $offer;
        });

        return response()->json([
            'message' => 'Store retrieved successfully',
            'success' => true,
            'data' => $store,
            
        ]);
    }

    /**
     * Find stores near a specific location
     */
    public function nearby(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:0.1|max:100', // in kilometers
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $radius = $request->radius ?? 10; // Default 10km radius
        $limit = $request->limit ?? 20;

        // Haversine formula to calculate distance
        $stores = Store::select('*')
            ->selectRaw('
                (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * 
                cos(radians(longitude) - radians(?)) + sin(radians(?)) * 
                sin(radians(latitude)))) AS distance
            ', [$latitude, $longitude, $latitude])
            ->where('is_active', true)
            ->having('distance', '<=', $radius)
            ->orderBy('distance')
            ->limit($limit)
            ->get();

     

        // Add image URLs to stores
        $stores->transform(function ($store) {
            $store->logo = $store->hasLogoBlob() 
                ? url("/api/images/store/{$store->id}/logo")
                : null;
            $store->banner_url = $store->hasBannerBlob() 
                ? url("/api/images/store/{$store->id}/banner")
                : null;
            return $store;
        });

        return response()->json([
            'message' => 'Nearby stores retrieved successfully',
            'data' => [
                'stores' => $stores,
                'search_location' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'radius_km' => $radius
                ]
            ]
        ]);
    }

    /**
     * Get store categories
     */
    public function categories(): JsonResponse
    {
        $categories = Store::where('is_active', true)
            ->select('category')
            ->distinct()
            ->whereNotNull('category')
            ->orderBy('category')
            ->pluck('category');

        return response()->json([
            'message' => 'Store categories retrieved successfully',
            'data' => $categories
        ]);
    }

    /**
     * Get store statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = Store::selectRaw('
            COUNT(*) as total_stores,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_stores,
            COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_stores,
            AVG(rating) as average_rating,
            COUNT(CASE WHEN rating >= 4.5 THEN 1 END) as highly_rated_stores,
            COUNT(CASE WHEN rating < 3 THEN 1 END) as low_rated_stores
        ')->first();

        // Get stores by category
        $byCategory = Store::where('is_active', true)
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderBy('count', 'desc')
            ->get();

        // Get top rated stores
        $topRated = Store::where('is_active', true)
            ->where('rating', '>=', 4.0)
            ->orderBy('rating', 'desc')
            ->limit(10)
            ->get(['id', 'name', 'rating', 'category']);

        return response()->json([
            'message' => 'Store statistics retrieved successfully',
            'data' => [
                'overview' => $stats,
                'by_category' => $byCategory,
                'top_rated' => $topRated,
            ]
        ]);
    }

    /**
     * Search stores with advanced filters
     */
    public function search(Request $request): JsonResponse
    {
        $query = Store::where('is_active', true);

        // Text search
        if ($request->has('q')) {
            $search = $request->q;
            $query->searchByEncryptedFields($search);
        }

        // Category filter
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Rating filter
        if ($request->has('min_rating')) {
            $query->where('rating', '>=', $request->min_rating);
        }

        // Location filters
        if ($request->has('city')) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        if ($request->has('state')) {
            $query->where('state', 'like', '%' . $request->state . '%');
        }

        // Filter by wilaya_code against store or its branches
        if ($request->has('wilaya_code')) {
            $wilayaCode = $request->get('wilaya_code');

            // Match stores whose primary wilaya matches OR have at least one active branch in this wilaya
            $query->where(function ($q) use ($wilayaCode) {
                $q->where('state', $wilayaCode)
                  ->orWhereExists(function ($sub) use ($wilayaCode) {
                      $sub->selectRaw('1')
                          ->from('store_branches')
                          ->whereColumn('store_branches.store_id', 'stores.id')
                          ->where('store_branches.is_active', true)
                          ->where('store_branches.wilaya_code', $wilayaCode);
                  });
            });

            // Also include offers scoped to branches in this wilaya
            $query->with(['offers' => function ($offerQ) use ($wilayaCode) {
                $offerQ->where('is_active', true)
                       ->where('valid_from', '<=', now())
                       ->where('valid_until', '>=', now())
                       ->whereHas('branches', function ($b) use ($wilayaCode) {
                           $b->where('wilaya_code', $wilayaCode);
                       });
            }]);
        }

        // Sort options
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        
        $allowedSortFields = ['name', 'rating', 'created_at', 'distance'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $stores = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'message' => 'Store search completed successfully',
            'data' => $stores
        ]);
    }

    /**
     * Get transactions for a specific store
     */
    public function transactions(Request $request, $storeId): JsonResponse
{
    \Log::info('StoreController::transactions - Store ID: ' . $storeId);

    // Start query
    $query = Purchase::where('store_id', $storeId)
        ->with(['user', 'offer']) // eager load related user & offer
        ->orderBy('purchase_date', 'desc');

    // Optional filter by status
    if ($request->has('status')) {
        $query->where('status', $request->status);
    }

    // Execute query
    $purchases = $query->get();

    \Log::info('StoreController::transactions - Purchases loaded', [
        'count' => $purchases->count(),
        'first_purchase_user' => $purchases->first() ? $purchases->first()->user : 'no_purchase',
        'first_purchase_user_id' => $purchases->first() ? $purchases->first()->user_id : 'no_purchase',
        'first_purchase_loaded_relationships' => $purchases->first() ? $purchases->first()->getRelations() : 'no_purchase',
        'first_purchase_raw' => $purchases->first() ? $purchases->first()->toArray() : 'no_purchase'
    ]);

    return response()->json([
        'message' => 'Store transactions retrieved successfully',
        'data' => $purchases
    ]);
}

    /**
     * Update business hours for a store (immediate, no admin approval)
     */
    public function updateBusinessHours(Request $request, $storeId): JsonResponse
    {
        try {
            $user = Auth::user();
            $store = Store::find($storeId);
            if (!$store) {
                return response()->json([
                    'message' => 'Store not found',
                    'data' => null,
                ], 404);
            }

            // Ensure the authenticated user owns this store
            if ($store->user_id !== $user->id && !$user->is_admin) {
                return response()->json([
                    'message' => 'Unauthorized',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'business_hours' => 'required|array|min:1',
                'business_hours.*.day' => 'required|string',
                'business_hours.*.open' => 'nullable|string',
                'business_hours.*.close' => 'nullable|string',
                'business_hours.*.is_closed' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $hours = $request->get('business_hours');

            // Save as-is (array of maps) in JSON column
            $store->business_hours = $hours;
            $store->save();

            return response()->json([
                'message' => 'Business hours updated successfully',
                'data' => $store->business_hours,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update business hours: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the authenticated store's store record
     */


    /**
     * List offers for authenticated store
     */
    public function myOffers(Request $request): JsonResponse
    {
        $user = Auth::user();
        $store = Store::where('user_id', $user->id)->first();
        
        $offers = Offer::where('store_id', $store->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return response()->json([
            'message' => 'Store offers retrieved successfully',
            'data' => $offers
        ]);
    }

    /**
     * Create offer for authenticated store
     */
    public function createMyOffer(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            \Log::info('createMyOffer called by user: ' . $user->id);
            
            $store = Store::where('user_id', $user->id)->first();
            \Log::info('Store found: ' . ($store ? $store->id : 'null'));

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'required|string|max:1000',
                'discount_type' => 'required|in:percentage,fixed',
                'discount_value' => 'required|numeric|min:0',
                'minimum_purchase' => 'nullable|numeric|min:0',
                'valid_from' => 'required|date',
                'valid_until' => 'required|date|after:valid_from',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                \Log::error('Validation failed: ' . json_encode($validator->errors()));
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->only([
                'title','description','discount_type','discount_value','minimum_purchase','valid_from','valid_until','is_active','image','terms'
            ]);
            $data['store_id'] = $store->id;
            
            \Log::info('Offer data before creation: ' . json_encode($data));
            
            // Ensure default values for required fields
            if (!isset($data['is_active'])) {
                $data['is_active'] = true;
            }
            if (!isset($data['valid_from'])) {
                $data['valid_from'] = now();
            }
            if (!isset($data['valid_until'])) {
                $data['valid_until'] = now()->addDays(30);
            }
            if (!isset($data['minimum_purchase'])) {
                $data['minimum_purchase'] = 0;
            }
            if (!isset($data['max_usage_per_user'])) {
                $data['max_usage_per_user'] = 1;
            }
            if (!isset($data['current_usage_count'])) {
                $data['current_usage_count'] = 0;
            }
            if (!isset($data['terms'])) {
                $data['terms'] = json_encode([]);
            } else {
                $data['terms'] = json_encode([$data['terms']]);
            }

            \Log::info('Final offer data: ' . json_encode($data));

            $offer = Offer::create($data);
            
            \Log::info('Offer created successfully with ID: ' . $offer->id);
            
            // Attach branches if provided
            if ($request->has('branch_ids') && is_array($request->branch_ids)) {
                $branchIds = array_map('intval', $request->branch_ids);
                $offer->branches()->sync($branchIds);
            }

            // Debug: Log the created offer
            \Log::info('Offer created', [
                'offer_id' => $offer->id,
                'store_id' => $offer->store_id,
                'title' => $offer->title,
                'is_active' => $offer->is_active,
                'valid_from' => $offer->valid_from,
                'valid_until' => $offer->valid_until,
                'store_approved' => $store->is_approved,
                'store_active' => $store->is_active
            ]);

            $response = [
                'message' => 'Offer created successfully',
                'data' => $offer
            ];
            
            \Log::info('Response data: ' . json_encode($response));

            return response()->json($response, 201);
        } catch (\Exception $e) {
            \Log::error('Error in createMyOffer: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'message' => 'Failed to create offer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update offer for authenticated store
     */
    public function updateMyOffer(Request $request, $offerId): JsonResponse
    {
        $userId = Auth::id();
        $store = Store::where('user_id', $userId)->first();
        
      
        
        // Manually fetch the offer to avoid route model binding issues
        $offer = Offer::find($offerId);
        if (!$offer) {
            return response()->json([
                'message' => 'Offer not found',
                'data' => null
            ], 404);
        }
        
        // If the offer doesn't belong to the user's store, update it to belong to the user's store
        if ($offer->store_id !== $store->id) {
            $offer->store_id = $store->id;
            $offer->save();
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:1000',
            'discount_type' => 'sometimes|in:percentage,fixed',
            'discount_value' => 'sometimes|numeric|min:0',
            'minimum_purchase' => 'sometimes|numeric|min:0',
            'valid_from' => 'sometimes|date',
            'valid_until' => 'sometimes|date|after:valid_from',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Debug: Log the request data and offer state
        \Log::info('UpdateMyOffer Request', [
            'request_data' => $request->all(),
            'offer_id' => $offer->id,
            'offer_exists' => $offer->exists,
            'offer_store_id' => $offer->store_id,
            'user_store_id' => $store->id
        ]);
        
        // Update individual fields to avoid any issues with the update method
        if ($request->has('title')) {
            $offer->title = $request->title;
        }
        if ($request->has('description')) {
            $offer->description = $request->description;
        }
        if ($request->has('discount_type')) {
            $offer->discount_type = $request->discount_type;
        }
        if ($request->has('discount_value')) {
            $offer->discount_value = $request->discount_value;
        }
        if ($request->has('minimum_purchase')) {
            $offer->minimum_purchase = $request->minimum_purchase;
        }
        if ($request->has('valid_from')) {
            $offer->valid_from = $request->valid_from;
        }
        if ($request->has('valid_until')) {
            $offer->valid_until = $request->valid_until;
        }
        if ($request->has('is_active')) {
            $offer->is_active = $request->is_active;
        }
        
        $offer->save();

        // Sync branches if provided
        if ($request->has('branch_ids') && is_array($request->branch_ids)) {
            $branchIds = array_map('intval', $request->branch_ids);
            $offer->branches()->sync($branchIds);
        }
 
        return response()->json([
            'message' => 'Offer updated successfully',
            'data' => $offer
        ]);
    }

    /**
     * Delete offer for authenticated store
     */
    public function deleteMyOffer(Request $request, $offerId): JsonResponse
    {
        $userId = Auth::id();
        $store = Store::where('user_id', $userId)->first();
        

        
        // Manually fetch the offer to avoid route model binding issues
        $offer = Offer::find($offerId);
        if (!$offer) {
            return response()->json([
                'message' => 'Offer not found',
                'data' => null
            ], 404);
        }
        
        // If the offer doesn't belong to the user's store, update it to belong to the user's store
        if ($offer->store_id !== $store->id) {
            $offer->store_id = $store->id;
            $offer->save();
        }

        $offer->delete();
        return response()->json([
            'message' => 'Offer deleted successfully'
        ]);
    }

    /**
     * Update store location for authenticated store
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = Auth::id();
        $store = Store::where('user_id', $userId)->first();
        
        if (!$store) {
            return response()->json([
                'message' => 'Store not found for user',
                'data' => null
            ], 404);
        }

        try {
            // Update location data
            $updateData = [
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'google_place_id' => $request->address,
            ];

         

            $store->update($updateData);

            return response()->json([
                'message' => 'Store location updated successfully',
                'data' => [
                    'latitude' => $store->latitude,
                    'longitude' => $store->longitude,
                    'google_place_id' => $store->address,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update store location: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
