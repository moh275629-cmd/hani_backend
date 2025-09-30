<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\LoyaltyCard;
use App\Models\Offer;
use App\Models\User;
use App\Http\Controllers\LoyaltyCardController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PurchaseController extends Controller
{
    /**
     * Display a listing of user's purchases
     */
    public function index(Request $request): JsonResponse
{
    $user = Auth::user();
    
    $query = Purchase::where('user_id', $user->id)
        ->with(['offer', 'store', 'user']);

    // Filter by status if provided
    if ($request->has('status')) {
        $query->where('status', $request->status);
    }

    // Filter by date range if provided
    if ($request->has('start_date')) {
        $query->where('purchase_date', '>=', $request->start_date);
    }
    if ($request->has('end_date')) {
        $query->where('purchase_date', '<=', $request->end_date);
    }

    // Filter by store if provided
    if ($request->has('store_id')) {
        $query->where('store_id', $request->store_id);
    }

    // Retrieve results
    $purchases = $query->orderBy('purchase_date', 'desc')->get();

    // Hide blob fields
    $purchases->transform(function ($purchase) {
        if ($purchase->offer) {
            $purchase->offer->makeHidden(['image_blob']);
        }
        if ($purchase->store) {
            $purchase->store->makeHidden(['logo_blob', 'banner_blob']);
        }
        return $purchase;
    });

    return response()->json([
        'message' => 'Purchases retrieved successfully',
        'data'    => $purchases
    ]);
}


    /**
     * Display the specified purchase
     */
    public function show(Purchase $purchase): JsonResponse
    {
        $user = Auth::user();
        
        // Ensure user can only view their own purchases
        if ($purchase->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized access',
                'data' => null
            ], 403);
        }

        $purchase->load(['offer', 'store','user']);

        // Ensure blob fields are not included in the response
        if ($purchase->offer) {
            $purchase->offer->makeHidden(['image_blob']);
        }
        if ($purchase->store) {
            $purchase->store->makeHidden(['logo_blob', 'banner_blob']);
        }

        return response()->json([
            'message' => 'Purchase retrieved successfully',
            'data' => $purchase
        ]);
    }

    /**
     * Store a newly created purchase
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'store_id' => 'required|exists:stores,id',
            'amount' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|integer|min:1',
            'product_name' => 'nullable|string|max:255',
            'details' => 'nullable|string|max:1000',
            'offer_id' => 'nullable|exists:offers,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user has an active loyalty card
        $loyaltyCard = LoyaltyCard::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$loyaltyCard) {
            return response()->json([
                'message' => 'No active loyalty card found',
                'data' => null
            ], 400);
        }

        // Calculate discount if offer is provided
        $discountAmount = 0;
        if ($request->offer_id) {
            $offer = Offer::find($request->offer_id);
            if ($offer && $offer->is_active && !$offer->isExpired()) {
                $discountAmount = $this->calculateDiscount($offer, $request->amount);
            }
        }

        // Calculate final amount
        $finalAmount = $request->amount - $discountAmount;

        // Create purchase record
        $purchase = Purchase::create([
            'user_id' => $user->id,
            'store_id' => $request->store_id,
            'redeemed_offer_id' => $request->offer_id,
            'purchase_number' => $this->generateTransactionId(),
            'products' => json_encode([['name' => $request->product_name ?? 'Purchase']]),
            'subtotal' => $request->amount,
            'discount_amount' => $discountAmount,
            'total_amount' => $finalAmount,
            'status' => 'completed',
            'purchase_date' => now(),
            'notes' => $request->details,
        ]);

        // Add points to loyalty card
        $points = (int) $finalAmount;
        $loyaltyCard->update([
            'points' => $loyaltyCard->points + $points,
            'total_spent' => $loyaltyCard->total_spent + $finalAmount,
            'visits' => $loyaltyCard->visits + 1,
            'last_used_at' => now(),
        ]);

        return response()->json([
            'message' => 'Purchase created successfully',
            'data' => [
                'purchase' => $purchase,
                'points_earned' => $points,
                'total_points' => $loyaltyCard->points + $points,
                'discount_amount' => $discountAmount,
                'final_amount' => $finalAmount,
            ]
        ], 201);
    }

    /**
     * Calculate discount amount based on offer type
     */
    private function calculateDiscount($offer, float $amount): float
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
     * Generate unique transaction ID
     */
    private function generateTransactionId(): string
    {
        return 'PUR' . time() . rand(1000, 9999);
    }

    /**
     * Get purchase statistics for user
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();
        
        $stats = Purchase::where('user_id', $user->id)
            ->selectRaw('
                COUNT(*) as total_purchases,
                SUM(total_amount) as total_spent,
                SUM(discount_amount) as total_savings,
                AVG(total_amount) as average_purchase,
                MAX(purchase_date) as last_purchase
            ')
            ->first();

        // Get monthly spending for the last 12 months
        $monthlySpending = Purchase::where('user_id', $user->id)
            ->where('purchase_date', '>=', now()->subMonths(12))
            ->selectRaw('
                DATE_FORMAT(purchase_date, "%Y-%m") as month,
                SUM(total_amount) as total_spent,
                COUNT(*) as purchase_count
            ')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'message' => 'Purchase statistics retrieved successfully',
            'data' => [
                'overview' => $stats,
                'monthly_spending' => $monthlySpending,
            ]
        ]);
    }

    /**
     * Refund a purchase (store side)
     */
    public function refund(Request $request,$purchaseId): JsonResponse
    {
        $user = Auth::user();
        $purchase = Purchase::find($purchaseId);
        // Check if user is store owner
        if ($user->role !== 'store') {
            return response()->json([
                'message' => 'Unauthorized. Only store owners can refund transactions.',
                'data' => null
            ], 403);
        }

        // Check if purchase belongs to this store
        $store = \App\Models\Store::where('user_id', $user->id)->first();
        if (!$store || $purchase->store_id !== $store->id) {
            return response()->json([
                'message' => 'Unauthorized. Purchase does not belong to your store.',
                'data' => null
            ], 403);
        }

        // Check if purchase can be refunded
        if (!$purchase->canBeRefunded()) {
            return response()->json([
                'message' => 'Purchase cannot be refunded. It may already be refunded or cancelled.',
                'data' => null
            ], 400);
        }

       

        try {
            \DB::beginTransaction();

            // Create refund record
            $refund = \App\Models\Refund::create([
                'purchase_id' => $purchaseId,
                'user_id' => $purchase->user_id,
                'store_id' => $purchase->store_id,
                'offer_id' => $purchase->redeemed_offer_id,
                'status' => 'completed',
                
            ]);

            // Mark purchase as refunded
            $purchase->markAsRefunded();
            $purchase->save();

            // Handle refund method
            switch ($request->refund_method) {
                case 'loyalty_points':
                    // Deduct points from loyalty card
                    $loyaltyCard = \App\Models\LoyaltyCard::where('user_id', $purchase->user_id)
                        ->where('is_active', true)
                        ->first();
                    if ($loyaltyCard) {
                        $pointsToDeduct = (int) $request->refund_amount;
                        $loyaltyCard->update([
                            'points' => max(0, $loyaltyCard->points - $pointsToDeduct),
                            'total_spent' => max(0, $loyaltyCard->total_spent - $request->refund_amount),
                        ]);
                    }
                    break;
                    
                case 'store_credit':
                    // Store credit logic would go here
                    break;
                    
                case 'original_payment':
                    // Original payment refund logic would go here
                    break;
            }

            // Create notification for customer
            \App\Models\Notification::createRefundNotification(
                $purchase->user_id,
                'refund',
                $refund->id
            );

            \DB::commit();

            return response()->json([
                'message' => 'Purchase refunded successfully',
                'data' => [
                    'refund' => $refund,
                    'purchase' => $purchase->fresh(),
                ]
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'message' => 'Failed to process refund: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Delete a purchase (store side) - only for pending/cancelled transactions
     */
    public function destroy(Purchase $purchase): JsonResponse
    {
        $user = Auth::user();
        
        // Check if user is store owner
        if ($user->role !== 'store') {
            return response()->json([
                'message' => 'Unauthorized. Only store owners can delete transactions.',
                'data' => null
            ], 403);
        }

        // Check if purchase belongs to this store
        $store = \App\Models\Store::where('user_id', $user->id)->first();
        if (!$store || $purchase->store_id !== $store->id) {
            return response()->json([
                'message' => 'Unauthorized. Purchase does not belong to your store.',
                'data' => null
            ], 403);
        }

        // Only allow deletion of pending or cancelled transactions
        if (!in_array($purchase->status, ['pending', 'cancelled'])) {
            return response()->json([
                'message' => 'Cannot delete completed or refunded transactions.',
                'data' => null
            ], 400);
        }

        try {
            $purchase->delete();
            
            return response()->json([
                'message' => 'Transaction deleted successfully',
                'data' => null
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete transaction: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
