<?php

namespace App\Http\Controllers;

use App\Models\RatingOffer;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OfferRatingController extends Controller
{
    /**
     * Rate an offer
     */
    public function rateOffer(Request $request, $offerId): JsonResponse
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

            // Check if offer exists
            $offer = Offer::find($offerId);
            if (!$offer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Offer not found'
                ], 404);
            }

            // Check if rating already exists
            $existingRating = RatingOffer::where('rater_id', $raterId)
                ->where('rated_offer_id', $offerId)
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
                    'data' => $existingRating->load('rater')
                ]);
            } else {
                // Create new rating
                $rating = RatingOffer::create([
                    'rater_id' => $raterId,
                    'rated_offer_id' => $offerId,
                    'stars' => $stars,
                    'comment' => $comment,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Rating submitted successfully',
                    'data' => $rating->load('rater')
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
     * Get ratings for a specific offer
     */
    public function getOfferRatings($offerId): JsonResponse
    {
        try {
            // Check if offer exists
            $offer = Offer::find($offerId);
            if (!$offer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Offer not found'
                ], 404);
            }

            // Get all ratings for this offer
            $ratings = RatingOffer::where('rated_offer_id', $offerId)
                ->with('rater')
                ->orderBy('created_at', 'desc')
                ->get();

            // Calculate rating statistics
            $totalRatings = $ratings->count();
            $averageRating = $totalRatings > 0 ? $ratings->avg('stars') : 0;
            
            // Calculate rating distribution
            $ratingDistribution = [];
            for ($i = 1; $i <= 5; $i++) {
                $count = $ratings->where('stars', $i)->count();
                $ratingDistribution[$i] = [
                    'count' => $count,
                    'percentage' => $totalRatings > 0 ? ($count / $totalRatings) * 100 : 0
                ];
            }

            $ratingSummary = [
                'average_rating' => round($averageRating, 1),
                'total_ratings' => $totalRatings,
                'rating_distribution' => $ratingDistribution
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'rating_summary' => $ratingSummary,
                    'ratings' => $ratings
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving ratings: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's rating for a specific offer
     */
    public function getUserOfferRating($offerId): JsonResponse
    {
        try {
            $raterId = Auth::id();

            // Check if offer exists
            $offer = Offer::find($offerId);
            if (!$offer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Offer not found'
                ], 404);
            }

            // Get user's rating for this offer
            $rating = RatingOffer::where('rater_id', $raterId)
                ->where('rated_offer_id', $offerId)
                ->with('rater')
                ->first();

            return response()->json([
                'success' => true,
                'data' => $rating
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving user rating: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
