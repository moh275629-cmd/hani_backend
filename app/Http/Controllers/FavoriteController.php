<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Favorite;
use App\Models\Store;
use App\Models\Offer;
use Illuminate\Support\Facades\Log;


class FavoriteController extends Controller
{
    /**
     * Add a favorite
     */
    public function addFavorite(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'favorable_type' => 'required|in:store,offer',
            'favorable_id' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $favorableType = $request->favorable_type;
        $favorableId = $request->favorable_id;

        // Check if the favorable item exists
        if ($favorableType === 'store') {
            $item = Store::find($favorableId);
        } else {
            $item = Offer::find($favorableId);
        }

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($favorableType) . ' not found'
            ], 404);
        }

        // Check if already favorited
        $existingFavorite = Favorite::where('user_id', $user->id)
            ->where('favorable_type', $favorableType)
            ->where('favorable_id', $favorableId)
            ->first();

        if ($existingFavorite) {
            return response()->json([
                'success' => false,
                'message' => 'Already added to favorites'
            ], 409);
        }

        try {
            $favorite = Favorite::create([
                'user_id' => $user->id,
                'favorable_type' => $favorableType,
                'favorable_id' => $favorableId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Added to favorites successfully',
                'data' => [
                    'id' => $favorite->id,
                    'favorable_type' => $favorite->favorable_type,
                    'favorable_id' => $favorite->favorable_id,
                    'created_at' => $favorite->created_at,
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding to favorites: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove a favorite by ID
     */
    public function removeFavoriteById(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        
        $favorite = Favorite::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$favorite) {
            return response()->json([
                'success' => false,
                'message' => 'Favorite not found'
            ], 404);
        }

        try {
            $favorite->delete();

            return response()->json([
                'success' => true,
                'message' => 'Removed from favorites successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error removing from favorites: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove a favorite by type and ID
     */
    public function removeFavoriteByType(Request $request): JsonResponse
    {
        \Log::info('Removing favorite by type: ' . $request->favorable_type . ' ' . $request->favorable_id);
        $validator = Validator::make($request->all(), [
            'favorable_type' => 'required|in:store,offer',
            'favorable_id' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $favorableType = $request->favorable_type;
        $favorableId = $request->favorable_id;

        $favorite = Favorite::where('user_id', $user->id)
            ->where('favorable_type', $favorableType)
            ->where('favorable_id', $favorableId)
            ->first();

        if (!$favorite) {
            return response()->json([
                'success' => false,
                'message' => 'Favorite not found'
            ], 404);
        }

        try {
            $favorite->delete();

            return response()->json([
                'success' => true,
                'message' => 'Removed from favorites successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error removing from favorites: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List user's favorites
     * Returns a flat array of favorites with favorable_type and favorable_id
     */
    public function listFavorites(Request $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $favorites = Favorite::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            $favoritesList = [];

            foreach ($favorites as $favorite) {
                $favoriteItem = [
                    'id' => $favorite->id,
                    'favorable_type' => $favorite->favorable_type,
                    'favorable_id' => $favorite->favorable_id,
                    'created_at' => $favorite->created_at,
                    'updated_at' => $favorite->updated_at,
                ];

                // Optionally include the actual item data
                if ($favorite->favorable_type === 'store') {
                    $store = Store::find($favorite->favorable_id);
                    if ($store) {
                        $favoriteItem['store'] = [
                            'id' => $store->id,
                            'store_name' => $store->store_name,
                            'description' => $store->description,
                            'address' => $store->address,
                            'city' => $store->city,
                            'state' => $store->state,
                            'phone' => $store->phone,
                            'email' => $store->email,
                            'website' => $store->website,
                            'facebook' => $store->facebook,
                            'instagram' => $store->instagram,
                            'tiktok' => $store->tiktok,
                            'business_gmail' => $store->business_gmail,
                            'logo' => $store->logo,
                            'main_image_url' => $store->main_image_url,
                            'is_active' => $store->is_active,
                            'is_approved' => $store->is_approved,
                        ];
                    }
                } elseif ($favorite->favorable_type === 'offer') {
                    $offer = Offer::with('store')->find($favorite->favorable_id);
                    if ($offer) {
                        $favoriteItem['offer'] = [
                            'id' => $offer->id,
                            'title' => $offer->title,
                            'description' => $offer->description,
                            'discount_type' => $offer->discount_type,
                            'discount_value' => $offer->discount_value,
                            'old_price' => $offer->old_price,
                            'minimum_purchase' => $offer->minimum_purchase,
                            'valid_from' => $offer->valid_from,
                            'valid_until' => $offer->valid_until,
                            'is_active' => $offer->is_active,
                            'is_featured' => $offer->is_featured,
                            'available_for_delivery' => $offer->available_for_delivery,
                            'image' => $offer->image,
                            'main_media_url' => $offer->main_media_url,
                            'store_id' => $offer->store_id,
                            'store_name' => $offer->store->store_name ?? null,
                        ];
                    }
                }

                $favoritesList[] = $favoriteItem;
            }

            return response()->json([
                'success' => true,
                'data' => $favoritesList,
                'meta' => [
                    'total' => count($favoritesList),
                    'total_stores' => $favorites->where('favorable_type', 'store')->count(),
                    'total_offers' => $favorites->where('favorable_type', 'offer')->count(),
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving favorites: ' . $e->getMessage()
            ], 500);
        }
    }
}