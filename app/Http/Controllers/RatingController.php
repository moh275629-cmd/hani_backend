<?php

namespace App\Http\Controllers;

use App\Models\Rating;
use App\Models\User;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class RatingController extends Controller
{
    /**
     * Rate a store (specific store rating endpoint)
     */
    public function rateStore(Request $request, $storeId): JsonResponse
    {
        try {
            Log::info('Rate store request received', ['storeId' => $storeId, 'request' => $request->all()]);
            // Validate request
            $validator = Validator::make($request->all(), [
                'stars' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $raterId = Auth::id();
            $stars = $request->input('stars');
            $comment = $request->input('comment');

            // Check if store exists and get its user ID
            $store = Store::find($storeId);
            if (!$store) {
                return response()->json([
                    'success' => false,
                    'message' => 'Store not found'
                ], 404);
            }

            $rateeId = $store->user_id;

            // Check if ratee user exists
            $ratee = User::find($rateeId);
            if (!$ratee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Store owner not found'
                ], 404);
            }

            // Prevent self-rating
            if ($raterId == $rateeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot rate your own store'
                ], 400);
            }

            // Check if rating already exists
            $existingRating = Rating::where('rater_id', $raterId)
                ->where('ratee_id', $rateeId)
                ->first();

            if ($existingRating) {
                // Update existing rating
                $existingRating->update([
                    'stars' => $stars,
                    'comment' => $comment,
                    'updated_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Store rating updated successfully',
                    'data' => $existingRating
                ]);
            } else {
                // Create new rating
                $rating = Rating::create([
                    'rater_id' => $raterId,
                    'ratee_id' => $rateeId,
                    'stars' => $stars,
                    'comment' => $comment,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Store rating submitted successfully',
                    'data' => $rating
                ], 201);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting store rating: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ratings for a specific store
     */
    public function getStoreRatings($storeId): JsonResponse
    {
        try {
            // Check if store exists and get its user ID
            $store = Store::find($storeId);
            if (!$store) {
                return response()->json([
                    'success' => false,
                    'message' => 'Store not found'
                ], 404);
            }

            $rateeId = $store->user_id;

            // Get all ratings for this store (via store owner user ID)
            $ratings = Rating::where('ratee_id', $rateeId)
                ->with(['rater:id,name,profile_image,role'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Calculate average rating
            $averageRating = $ratings->avg('stars') ?? 0;
            $totalRatings = $ratings->count();

            // Group ratings by stars
            $ratingDistribution = [];
            for ($i = 1; $i <= 5; $i++) {
                $ratingDistribution[$i] = $ratings->where('stars', $i)->count();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'store' => [
                        'id' => $store->id,
                        'name' => $store->store_name,
                        'user_id' => $store->user_id,
                    ],
                    'rating_summary' => [
                        'average_rating' => round($averageRating, 1),
                        'total_ratings' => $totalRatings,
                        'rating_distribution' => $ratingDistribution,
                    ],
                    'ratings' => $ratings->map(function ($rating) {
                        return [
                            'id' => $rating->id,
                            'stars' => $rating->stars,
                            'comment' => $rating->comment,
                            'created_at' => $rating->created_at,
                            'rater' => [
                                'id' => $rating->rater->id,
                                'name' => $rating->rater->name,
                                'role' => $rating->rater->role,
                                'profile_image' => $rating->rater->profile_image,
                            ]
                        ];
                    })
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving store ratings: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current user's rating for a specific store
     */
    public function getUserStoreRating($storeId): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            // Check if store exists and get its user ID
            $store = Store::find($storeId);
            if (!$store) {
                return response()->json([
                    'success' => false,
                    'message' => 'Store not found'
                ], 404);
            }

            $rateeId = $store->user_id;

            // Get user's rating for this store
            $rating = Rating::where('rater_id', $userId)
                ->where('ratee_id', $rateeId)
                ->first();

            return response()->json([
                'success' => true,
                'data' => $rating ? [
                    'id' => $rating->id,
                    'stars' => $rating->stars,
                    'comment' => $rating->comment,
                    'created_at' => $rating->created_at,
                    'updated_at' => $rating->updated_at,
                ] : null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving user store rating: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rate a user (store or client) - UPDATED with better logic
     */
    public function rateUser(Request $request, $rateeId): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'stars' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $raterId = Auth::id();
            $stars = $request->input('stars');
            $comment = $request->input('comment');

            // Check if ratee exists - try as user first, then as store
            $ratee = User::find($rateeId);
            
            if (!$ratee && $ratee->role == 'store') {
                // Try to find as store and get the store's user ID
                $store = Store::find($rateeId);
                if ($store) {
                    $rateeId = $store->user_id;
                    $ratee = User::find($rateeId);
                }
            }

            if (!$ratee) {
                return response()->json([
                    'success' => false,
                    'message' => 'User or Store not found'
                ], 404);
            }

            // Prevent self-rating
            if ($raterId == $rateeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot rate yourself'
                ], 400);
            }

            // Check if rating already exists
            $existingRating = Rating::where('rater_id', $raterId)
                ->where('ratee_id', $rateeId)
                ->first();

            if ($existingRating) {
                // Update existing rating
                $existingRating->update([
                    'stars' => $stars,
                    'comment' => $comment,
                    'updated_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Rating updated successfully',
                    'data' => $existingRating
                ]);
            } else {
                // Create new rating
                $rating = Rating::create([
                    'rater_id' => $raterId,
                    'ratee_id' => $rateeId,
                    'stars' => $stars,
                    'comment' => $comment,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Rating submitted successfully',
                    'data' => $rating
                ], 201);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting rating: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ratings for a specific user - UPDATED with better logic
     */
    public function getUserRatings($rateeId): JsonResponse
    {
        try {
            // Check if user exists - try as user first, then as store
            $ratee = User::find($rateeId);
            $isStore = false;
            $store = null;
            
            if (!$ratee) {
                // Try to find as store and get the store's user ID
                $store = Store::find($rateeId);
                if ($store) {
                    $rateeId = $store->user_id;
                    $ratee = User::find($rateeId);
                    $isStore = true;
                }
            }

            if (!$ratee) {
                return response()->json([
                    'success' => false,
                    'message' => 'User or Store not found'
                ], 404);
            }

            // Get all ratings for this user
            $ratings = Rating::where('ratee_id', $rateeId)
                ->with(['rater:id,name,profile_image,role'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Calculate average rating
            $averageRating = $ratings->avg('stars') ?? 0;
            $totalRatings = $ratings->count();

            // Group ratings by stars
            $ratingDistribution = [];
            for ($i = 1; $i <= 5; $i++) {
                $ratingDistribution[$i] = $ratings->where('stars', $i)->count();
            }

            $responseData = [
                'user' => [
                    'id' => $ratee->id,
                    'name' => $ratee->name,
                    'role' => $ratee->role,
                ],
                'rating_summary' => [
                    'average_rating' => round($averageRating, 1),
                    'total_ratings' => $totalRatings,
                    'rating_distribution' => $ratingDistribution,
                ],
                'ratings' => $ratings->map(function ($rating) {
                    return [
                        'id' => $rating->id,
                        'stars' => $rating->stars,
                        'comment' => $rating->comment,
                        'created_at' => $rating->created_at,
                        'rater' => [
                            'id' => $rating->rater->id,
                            'name' => $rating->rater->name,
                            'role' => $rating->rater->role,
                            'profile_image' => $rating->rater->profile_image,
                        ]
                    ];
                })
            ];

            // Add store info if this was a store request
            if ($isStore && $store) {
                $responseData['store'] = [
                    'id' => $store->id,
                    'store_name' => $store->store_name,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $responseData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving ratings: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }
}