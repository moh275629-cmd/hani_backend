<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyCard;
use App\Models\User;
use App\Models\Purchase;
use App\Http\Controllers\LoyaltyCardController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class QRController extends Controller
{
    /**
     * Check if the authenticated user is a client
     */
    private function ensureClientUser()
    {
        if (auth()->user()->role !== 'client') {
            abort(403, 'Access denied. Only client users can generate loyalty card QR codes.');
        }
    }

    /**
     * Scan a QR code (public endpoint for store scanning)
     */
    public function scan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'qr_data' => 'required|string',
            'store_id' => 'required|exists:stores,id',
            'notes' => 'nullable|string|max:1000',
            'offer_id' => 'required|exists:offers,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed' . $validator->errors() ,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Parse QR data
            $qrData = json_decode($request->qr_data, true);
            
            \Log::info('QR scan request received', [
                'qr_data' => $request->qr_data,
                'parsed_qr_data' => $qrData,
                'store_id' => $request->store_id,
                'offer_id' => $request->offer_id
            ]);

            if (!$qrData) {
                return response()->json([
                    'message' => 'Invalid JSON format in QR data',
                    'data' => null
                ], 400);
            }

            // Validate QR code format
            if (!isset($qrData['card_number']) || !isset($qrData['user_id']) || !isset($qrData['timestamp'])) {
                return response()->json([
                    'message' => 'Invalid JSON QR code format - missing required fields',
                    'data' => null
                ], 400);
            }

            \Log::info('QR code validation passed', [
                'card_number' => $qrData['card_number'],
                'user_id' => $qrData['user_id'],
                'timestamp' => $qrData['timestamp'],
                'store_id_in_qr' => $qrData['store_id'] ?? 'not_present'
            ]);

            // Find loyalty card by card number
            $loyaltyCard = LoyaltyCard::where('card_number', $qrData['card_number'])
                ->with('user')
                ->first();

            \Log::info('Loyalty card lookup', [
                'searched_card_number' => $qrData['card_number'],
                'loyalty_card_found' => $loyaltyCard ? 'yes' : 'no',
                'loyalty_card_id' => $loyaltyCard ? $loyaltyCard->id : null,
                'loyalty_card_user_id' => $loyaltyCard ? $loyaltyCard->user_id : null,
                
            ]);

            if (!$loyaltyCard) {
                \Log::error('Loyalty card not found or inactive', [
                    'card_number' => $qrData['card_number'],
                    'user_id' => $qrData['user_id']
                ]);
                return response()->json([
                    'message' => 'Loyalty card not found or inactive',
                    'data' => null
                ], 404);
            }

            // Verify loyalty card has user relationship
            if (!$loyaltyCard->user) {
                \Log::error('Loyalty card missing user relationship', ['card_id' => $loyaltyCard->id]);
                return response()->json([
                    'message' => 'Loyalty card user relationship error',
                    'data' => null
                ], 500);
            }

            // Check if user is active
         

            // Get or create a default branch for the store
            $storeId = $request->store_id;
            \Log::info('Branch handling', ['requested_branch_id' => $storeId, 'store_id' => $request->store_id]);
            
            // Verify store exists
            $store = \App\Models\Store::find($request->store_id);
            if (!$store) {
                \Log::error('Store not found', ['store_id' => $request->store_id]);
                return response()->json([
                    'message' => 'Store not found',
                    'data' => null
                ], 404);
            }
            
          
            
            try {
                $purchaseData = [
                    'user_id' => $loyaltyCard->user_id,
                    'store_id' => $storeId,
                    'redeemed_offer_id' => $request->offer_id,
                    'purchase_number' => $this->generateTransactionId(),
                    'products' => json_encode([
                        [
                            'name' => 'Store Purchase',
                            'quantity' => 1,
                            'price' => 0,
                        ],
                    ]),
                    'subtotal' => 0,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'total_amount' => 0,
                    'points_earned' => 0,
                    'points_spent' => 0,
                    'status' => 'completed',
                    'payment_method' => 'cash',
                    'notes' => $request->notes,
                    'purchase_date' => now(),
                ];
                
                \Log::info('Purchase data prepared', $purchaseData);
                
                $purchase = Purchase::create($purchaseData);
                \Log::info('Purchase created successfully', ['purchase_id' => $purchase->id]);
            } catch (\Exception $e) {
                \Log::error('Failed to create purchase', [
                    'error' => $e->getMessage(), 
                    'trace' => $e->getTraceAsString(),
                    'purchase_data' => $purchaseData ?? 'not set'
                ]);
                throw $e;
            }

            // Update offer usage if offer was applied
            if ($request->offer_id) {
                try {
                    $offer = \App\Models\Offer::find($request->offer_id);
                    if ($offer) {
                        $offer->increment('current_usage_count');
                        \Log::info('Offer usage incremented', ['offer_id' => $request->offer_id]);
                    } else {
                        \Log::warning('Offer not found for usage increment', ['offer_id' => $request->offer_id]);
                    }
                } catch (\Exception $e) {
                    \Log::error('Failed to increment offer usage', ['error' => $e->getMessage()]);
                    // Don't fail the transaction for offer usage errors
                }
            }

            // Update loyalty card last used timestamp
            try {
            $loyaltyCard->update([
                'last_used_at' => now(),
            ]);
                \Log::info('Loyalty card last used timestamp updated', ['card_id' => $loyaltyCard->id]);
            } catch (\Exception $e) {
                \Log::error('Failed to update loyalty card timestamp', ['error' => $e->getMessage()]);
                // Don't fail the transaction for timestamp update errors
            }

            return response()->json([
                'message' => 'QR code scanned successfully',
                'data' => [
                    'user_name' => $loyaltyCard->user->name,
                    'card_number' => $loyaltyCard->card_number,
                    'purchase_number' => $purchase->purchase_number,
                    'offer_applied' => $request->offer_id ? true : false,
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('QR scan processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'qr_data' => $request->qr_data,
                'store_id' => $request->store_id
            ]);
            
            
            return response()->json([
                'message' => 'Error processing QR code: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display QR code information (public endpoint)
     */
    public function show(string $code): JsonResponse
    {
        $loyaltyCard = LoyaltyCard::where('card_number', $code)
            ->with('user')
            ->first();

        if (!$loyaltyCard) {
            return response()->json([
                'message' => 'Loyalty card not found',
                'data' => null
            ], 404);
        }

        // Return limited information for security
        return response()->json([
            'message' => 'Loyalty card found',
            'data' => [
                'card_number' => $loyaltyCard->card_number,
                'user_name' => $loyaltyCard->user->name,
                
                'last_used' => $loyaltyCard->last_used_at,
            ]
        ]);
    }

    /**
     * Generate QR code for authenticated user
     */
    public function generate(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized',
                'data' => null
            ], 401);
        }

        // Only client users can generate loyalty card QR codes
        $this->ensureClientUser();

        $loyaltyCard = LoyaltyCard::where('user_id', $user->id)
            ->first();

        if (!$loyaltyCard) {
            return response()->json([
                'message' => 'No active loyalty card found',
                'data' => null
            ], 404);
        }

        // Generate new QR code data
        $qrData = [
            'card_number' => $loyaltyCard->card_number,
            'user_id' => $user->id,
            'store_id' => $loyaltyCard->store_id,
            'timestamp' => time()
        ];

        // Update QR code in database
        $loyaltyCard->update([
            'qr_code' => json_encode($qrData)
        ]);

        return response()->json([
            'message' => 'QR code generated successfully',
            'data' => [
                'qr_data' => $qrData,
                'card_number' => $loyaltyCard->card_number,
                'points' => $loyaltyCard->points,
            ]
        ]);
    }

    /**
     * Validate QR code (for testing purposes)
     */
    public function validate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'qr_data' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $qrData = json_decode($request->qr_data, true);
            
            if (!$qrData) {
                return response()->json([
                    'message' => 'Invalid JSON format',
                    'valid' => false
                ], 400);
            }

            // Check required fields
            $requiredFields = ['card_number', 'user_id', 'timestamp'];
            foreach ($requiredFields as $field) {
                if (!isset($qrData[$field])) {
                    return response()->json([
                        'message' => "Missing required field: {$field}",
                        'valid' => false
                    ], 400);
                }
            }

            // Check if QR code is not too old
            $age = time() - $qrData['timestamp'];
            $notExpired = $age <= 86400; // 24 hours

            // Check if loyalty card exists and is active
            $loyaltyCard = LoyaltyCard::where('card_number', $qrData['card_number'])
                ->first();
            
            $cardExists = $loyaltyCard !== null;

            return response()->json([
                'message' => 'QR code validation completed',
                'data' => [
                    'valid' => $notExpired && $cardExists,
                    'not_expired' => $notExpired,
                    'card_exists' => $cardExists,
                    'age_hours' => round($age / 3600, 2),
                    'card_number' => $qrData['card_number'],
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error validating QR code',
                'error' => $e->getMessage(),
                'valid' => false
            ], 500);
        }
    }

    /**
     * Generate unique transaction ID
     */
    private function generateTransactionId(): string
    {
        return 'PN' . time() . rand(1000, 9999);
    }
}
