<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyCard;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LoyaltyCardController extends Controller
{
    /**
     * Check if the authenticated user is a client
     */
    private function ensureClientUser()
    {
        if (auth()->user()->role !== 'client') {
            abort(403, 'Access denied. Only client users can access loyalty cards.');
        }
    }

    /**
     * Display the user's loyalty card
     */
    public function show(): JsonResponse
    {
        try {
            $this->ensureClientUser();
            $user = Auth::user();
            $loyaltyCard = LoyaltyCard::where('user_id', $user->id)->first();

            if (!$loyaltyCard) {
                // Auto-create a loyalty card for the user if it does not exist
                $cardNumber = $this->generateCardNumber($user->id, $user->state);
                $loyaltyCard = LoyaltyCard::create([
                    'user_id' => $user->id,
                    'card_number' => $cardNumber,
                    'qr_code' => $this->generateQRCode($user->id, $cardNumber, null),
                ]);
            } else {
                // Check if existing QR code is properly formatted
                $qrData = json_decode($loyaltyCard->qr_code, true);
                if (!$qrData || !isset($qrData['user_id']) || !isset($qrData['card_number']) || !isset($qrData['timestamp'])) {
                    // Regenerate QR code if it's not properly formatted
                    $loyaltyCard->update([
                        'qr_code' => $this->generateQRCode($user->id, $loyaltyCard->card_number, $loyaltyCard->store_id)
                    ]);
                    $loyaltyCard->refresh();
                }
            }

            return response()->json([
                'message' => 'Loyalty card retrieved successfully',
                'data' => $loyaltyCard
            ]);
        } catch (\Exception $e) {
            \Log::error('Loyalty card show error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Server Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activate a new loyalty card for the user
     */
    public function activate(Request $request): JsonResponse
    {
        $this->ensureClientUser();
        $user = Auth::user();
        
        // Check if user already has a loyalty card
        $existingCard = LoyaltyCard::where('user_id', $user->id)->first();
        if ($existingCard) {
            return response()->json([
                'message' => 'User already has an active loyalty card',
                'data' => $existingCard
            ], 400);
        }

        // Generate unique card number
        $cardNumber = $this->generateCardNumber($user->id, $user->state);
        
        // Create new loyalty card
        $loyaltyCard = LoyaltyCard::create([
            'user_id' => $user->id,
            'store_id' => null, // Global loyalty card
            'card_number' => $cardNumber,
            'qr_code' => $this->generateQRCode($user->id, $cardNumber, null),
        ]);

        return response()->json([
            'message' => 'Loyalty card activated successfully',
            'data' => $loyaltyCard
        ], 201);
    }

    /**
     * Get loyalty card transaction history
     */
    public function transactions(Request $request): JsonResponse
    {
        $this->ensureClientUser();
        $user = Auth::user();
        $loyaltyCard = LoyaltyCard::where('user_id', $user->id)->first();

        if (!$loyaltyCard) {
            return response()->json([
                'message' => 'No loyalty card found',
                'data' => []
            ], 404);
        }

        $transactions = Purchase::where('user_id', $user->id)
            ->with(['branch', 'offer'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'message' => 'Transactions retrieved successfully',
            'data' => $transactions
        ]);
    }

    /**
     * Generate a unique loyalty card number
     */
    private function generateCardNumber(int $userId, int $userState): string
{
    $year = date('Y'); // e.g. 2025
    $yearShort = substr($year, -2); // "25"

    // Build the base numeric string first
    $base = $yearShort . str_pad($userState, 2, '0', STR_PAD_LEFT) . $userId;

    // If you want to ensure a minimum length with leading zeros
    $padded = str_pad($base, 6, '0', STR_PAD_LEFT);

    return 'HANI' . $padded;
}


    /**
     * Generate QR code with proper data structure
     */
    private function generateQRCode(int $userId, string $cardNumber, $storeId = null): string
    {
        return LoyaltyCard::generateQrCode($userId, $cardNumber, $storeId);
    }

    /**
     * Regenerate QR code for existing loyalty card
     */
    public function regenerateQRCode(): JsonResponse
    {
        $this->ensureClientUser();
        $user = Auth::user();
        $loyaltyCard = LoyaltyCard::where('user_id', $user->id)->first();

        if (!$loyaltyCard) {
            return response()->json([
                'message' => 'No loyalty card found',
                'data' => null
            ], 404);
        }

        // Generate new QR code with proper data
        $newQrCode = $this->generateQRCode($user->id, $loyaltyCard->card_number, $loyaltyCard->store_id);
        
        $loyaltyCard->update([
            'qr_code' => $newQrCode
        ]);

        return response()->json([
            'message' => 'QR code regenerated successfully',
            'data' => [
                'qr_code' => $newQrCode,
                'card_number' => $loyaltyCard->card_number,
                'user_id' => $user->id
            ]
        ]);
    }


    /**
     * Add points to loyalty card
     */
    public function addPoints(int $points, float $amount): void
    {
        $this->ensureClientUser();
        $user = Auth::user();
        $loyaltyCard = LoyaltyCard::where('user_id', $user->id)->first();

        if ($loyaltyCard) {
            // Note: Points functionality requires additional database fields
            // For now, just log the points addition
            \Log::info("Points added to loyalty card: {$points} points for user {$user->id}");
        }
    }

    /**
     * Deduct points from loyalty card
     */
    public function deductPoints(int $points): bool
    {
        $this->ensureClientUser();
        $user = Auth::user();
        $loyaltyCard = LoyaltyCard::where('user_id', $user->id)->first();

        if ($loyaltyCard) {
            // Note: Points functionality requires additional database fields
            // For now, just log the points deduction
            \Log::info("Points deducted from loyalty card: {$points} points for user {$user->id}");
            return true;
        }

        return false;
    }
}
