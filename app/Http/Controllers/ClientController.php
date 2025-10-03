<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\ClientRating;
use App\Models\ClientReport;
use App\Models\Purchase;

class ClientController extends Controller
{
    /**
     * Get public client profile
     */
    public function getPublicProfile(Request $request, $userId): JsonResponse
    {
        try {
            $user = User::find($userId);
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            // Get rating summary
            $ratings = ClientRating::where('user_id', $userId)->get();
            $averageRating = $ratings->avg('rating');
            $totalRatings = $ratings->count();
            
            $ratingDistribution = [];
            for ($i = 1; $i <= 5; $i++) {
                $ratingDistribution[$i] = $ratings->where('rating', $i)->count();
            }

            // Get recent orders summary (last 10 purchases)
            $recentOrders = Purchase::where('user_id', $userId)
                ->with(['store', 'redeemedOffer'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($purchase) {
                    return [
                        'id' => $purchase->id,
                        'amount' => $purchase->amount,
                        'points_earned' => $purchase->points_earned,
                        'created_at' => $purchase->created_at,
                        'store' => $purchase->store ? [
                            'id' => $purchase->store->id,
                            'store_name' => $purchase->store->store_name,
                        ] : null,
                        'offer' => $purchase->redeemedOffer ? [
                            'id' => $purchase->redeemedOffer->id,
                            'title' => $purchase->redeemedOffer->title,
                        ] : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'profile_image' => $user->profile_image,
                        'email' => $user->email, // Only show if user allows it
                        'phone' => $user->phone, // Only show if user allows it
                        'created_at' => $user->created_at,
                    ],
                    'rating_summary' => [
                        'average_rating' => round($averageRating, 1),
                        'total_ratings' => $totalRatings,
                        'rating_distribution' => $ratingDistribution,
                    ],
                    'recent_orders' => $recentOrders,
                    'total_orders' => Purchase::where('user_id', $userId)->count(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving client profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rate a client
     */
    public function rateClient(Request $request, $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $store = $request->user()->store;
        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found'
            ], 404);
        }

       
        $rating = $request->rating;
        $comment = $request->comment;

        // Check if store has interacted with this client (via purchases)
        $hasInteraction = Purchase::where('user_id', $userId)
            ->where('store_id', $store->id)
            ->exists();

        if (!$hasInteraction) {
            return response()->json([
                'success' => false,
                'message' => 'You can only rate clients you have interacted with'
            ], 403);
        }

        try {
            // Update or create rating
            $clientRating = ClientRating::updateOrCreate(
                [
                    'store_id' => $store->id,
                    'user_id' => $userId,
                ],
                [
                    'rating' => $rating,
                    'comment' => $comment,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Client rated successfully',
                'data' => [
                    'id' => $clientRating->id,
                    'rating' => $clientRating->rating,
                    'comment' => $clientRating->comment,
                    'created_at' => $clientRating->created_at,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rating client: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Report a client
     */
    public function reportClient(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'reason' => 'required|string|max:255',
            'details' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $store = $request->user()->store;
        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found'
            ], 404);
        }

        $userId = $request->user_id;
        $reason = $request->reason;
        $details = $request->details;

        // Check if store has interacted with this client (via purchases)
        $hasInteraction = Purchase::where('user_id', $userId)
            ->where('store_id', $store->id)
            ->exists();

        if (!$hasInteraction) {
            return response()->json([
                'success' => false,
                'message' => 'You can only report clients you have interacted with'
            ], 403);
        }

        try {
            $clientReport = ClientReport::create([
                'reporter_store_id' => $store->id,
                'reported_user_id' => $userId,
                'reason' => $reason,
                'details' => $details,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Client reported successfully',
                'data' => [
                    'id' => $clientReport->id,
                    'reason' => $clientReport->reason,
                    'status' => $clientReport->status,
                    'created_at' => $clientReport->created_at,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error reporting client: ' . $e->getMessage()
            ], 500);
        }
    }
}
