<?php

namespace App\Http\Controllers;

use App\Models\Rating;
use App\Models\User;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RatingController extends Controller
{
    /**
     * Rate a user (store or client)
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

            // Check if ratee exists
            \Log::info($rateeId);
            \Log::info($raterId);
            $ratee = User::find($raterId);
            \Log::info($ratee);
            if (!$ratee) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found1'
                ], 404);
            }
            $store = Store::find($rateeId);
            $rateeId = $store->user_id; 

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
                'message' => 'Error submitting rating' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ratings for a specific user
     */
    public function getUserRatings($rateeId): JsonResponse
    {
        try {
            // Check if user exists
            $store = Store::find($rateeId);
            $rateeId = $store->user_id;
            $ratee = User::find($rateeId);
            if (!$ratee) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found2'
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

            return response()->json([
                'success' => true,
                'data' => [
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
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving ratings' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
