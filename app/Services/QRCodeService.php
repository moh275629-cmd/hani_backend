<?php

namespace App\Services;

use App\Models\LoyaltyCard;
use App\Models\User;
use Illuminate\Support\Str;

class QRCodeService
{
    /**
     * Generate QR code data for a user's loyalty card
     */
    public function generateQRData(User $user): array
    {
        // Only client users can have loyalty cards
        if ($user->role !== 'client') {
            throw new \Exception('Access denied. Only client users can have loyalty cards.');
        }
        
        $loyaltyCard = $user->loyaltyCard;
        
        if (!$loyaltyCard || !$loyaltyCard->is_active) {
            throw new \Exception('User does not have an active loyalty card');
        }

        $timestamp = time();
        $cardNumber = $loyaltyCard->card_number;
        $userId = $user->id;
        $storeId = $loyaltyCard->store_id;

        return [
            'card_number' => $cardNumber,
            'user_id' => $userId,
            'store_id' => $storeId,
            'timestamp' => $timestamp,
            'expires_at' => $timestamp + 86400, // 24 hours
        ];
    }

    /**
     * Validate QR code data
     */
    public function validateQRData(array $qrData): bool
    {
        // Check required fields
        if (!isset($qrData['card_number'], $qrData['user_id'], $qrData['timestamp'])) {
            return false;
        }

        // Check if QR code is not expired
        if (time() - $qrData['timestamp'] > 86400) {
            return false;
        }

        return true;
    }

    /**
     * Process QR code scan and return user information
     */
    public function processQRScan(array $qrData): array
    {
        if (!$this->validateQRData($qrData)) {
            throw new \Exception('Invalid or expired QR code');
        }

        $loyaltyCard = LoyaltyCard::where('card_number', $qrData['card_number'])
            ->where('is_active', true)
            ->with('user')
            ->first();

        if (!$loyaltyCard) {
            throw new \Exception('Loyalty card not found or inactive');
        }

        if (!$loyaltyCard->user->is_active) {
            throw new \Exception('User account is inactive');
        }

        return [
            'user' => [
                'id' => $loyaltyCard->user->id,
                'name' => $loyaltyCard->user->name,
                'email' => $loyaltyCard->user->email,
                'phone' => $loyaltyCard->user->phone,
            ],
            'loyalty_card' => [
                'card_number' => $loyaltyCard->card_number,
                'points_balance' => $loyaltyCard->points_balance,
                'card_type' => $loyaltyCard->card_type,
                'is_active' => $loyaltyCard->is_active,
            ],
            'scan_timestamp' => time(),
        ];
    }

    /**
     * Generate a unique transaction ID
     */
    public function generateTransactionId(): string
    {
        return 'TXN' . date('Ymd') . Str::random(8) . time();
    }

    /**
     * Calculate points for a purchase amount
     */
    public function calculatePoints(float $amount): int
    {
        // 1 point per dollar spent
        return (int) $amount;
    }

    /**
     * Check if QR code is valid for a specific store
     */
    public function isQRValidForStore(array $qrData, int $storeId): bool
    {
        try {
            $userData = $this->processQRScan($qrData);
            
            // Check if user has any restrictions for this store
            // This could be expanded to include store-specific validations
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
