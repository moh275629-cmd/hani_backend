<?php

namespace App\Services;

use App\Models\LoyaltyCard;
use App\Models\Purchase;
use App\Models\Offer;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    /**
     * Calculate and award points for a purchase
     */
    public function awardPointsForPurchase(Purchase $purchase): int
    {
        $loyaltyCard = $purchase->user->loyaltyCard;
        
        if (!$loyaltyCard || !$loyaltyCard->is_active) {
            return 0;
        }

        // Base points: 1 point per dollar spent
        $basePoints = (int) $purchase->final_amount;
        
        // Apply tier multiplier
        $tierMultiplier = $this->getTierMultiplier($loyaltyCard->tier);
        $totalPoints = $basePoints * $tierMultiplier;
        
        // Update loyalty card points
        $loyaltyCard->increment('points_balance', $totalPoints);
        
        // Check for tier upgrade
        $this->checkAndUpgradeTier($loyaltyCard);
        
        return $totalPoints;
    }

    /**
     * Get tier multiplier for points calculation
     */
    private function getTierMultiplier(string $tier): float
    {
        return match ($tier) {
            'bronze' => 1.0,
            'silver' => 1.2,
            'gold' => 1.5,
            'platinum' => 2.0,
            default => 1.0,
        };
    }

    /**
     * Check if user should be upgraded to a higher tier
     */
    private function checkAndUpgradeTier(LoyaltyCard $loyaltyCard): void
    {
        $currentTier = $loyaltyCard->tier;
        $pointsBalance = $loyaltyCard->points_balance;
        
        $newTier = $this->calculateTier($pointsBalance);
        
        if ($newTier !== $currentTier) {
            $loyaltyCard->update(['tier' => $newTier]);
            
            // Could trigger notifications or other tier upgrade benefits here
        }
    }

    /**
     * Calculate tier based on points balance
     */
    private function calculateTier(int $pointsBalance): string
    {
        return match (true) {
            $pointsBalance >= 10000 => 'platinum',
            $pointsBalance >= 5000 => 'gold',
            $pointsBalance >= 2000 => 'silver',
            default => 'bronze',
        };
    }

    /**
     * Process offer redemption
     */
    public function processOfferRedemption(LoyaltyCard $loyaltyCard, Offer $offer): array
    {
        if (!$offer->is_active) {
            throw new \Exception('Offer is not active');
        }

        if ($offer->current_redemptions >= $offer->max_redemptions) {
            throw new \Exception('Offer redemption limit reached');
        }

        if ($loyaltyCard->points_balance < $offer->points_required) {
            throw new \Exception('Insufficient points to redeem this offer');
        }

        // Deduct points
        $loyaltyCard->decrement('points_balance', $offer->points_required);
        
        // Increment offer redemptions
        $offer->increment('current_redemptions');
        
        return [
            'points_deducted' => $offer->points_required,
            'remaining_points' => $loyaltyCard->points_balance,
            'offer_details' => [
                'title' => $offer->title,
                'description' => $offer->description,
                'discount_type' => $offer->discount_type,
                'discount_value' => $offer->discount_value,
            ],
        ];
    }

    /**
     * Get user's loyalty statistics
     */
    public function getUserLoyaltyStats(int $userId): array
    {
        $loyaltyCard = LoyaltyCard::where('user_id', $userId)->first();
        
        if (!$loyaltyCard) {
            return [];
        }

        $totalPurchases = Purchase::where('user_id', $userId)->count();
        $totalSpent = Purchase::where('user_id', $userId)->sum('final_amount');
        $totalPointsEarned = Purchase::where('user_id', $userId)->sum(DB::raw('final_amount * 1')); // Simplified calculation
        
        $offersRedeemed = DB::table('offer_redemptions')
            ->where('user_id', $userId)
            ->count();

        return [
            'current_tier' => $loyaltyCard->tier,
            'points_balance' => $loyaltyCard->points_balance,
            'total_purchases' => $totalPurchases,
            'total_spent' => $totalSpent,
            'total_points_earned' => $totalPointsEarned,
            'offers_redeemed' => $offersRedeemed,
            'next_tier_threshold' => $this->getNextTierThreshold($loyaltyCard->tier),
        ];
    }

    /**
     * Get points needed for next tier
     */
    private function getNextTierThreshold(string $currentTier): int
    {
        return match ($currentTier) {
            'bronze' => 2000,
            'silver' => 5000,
            'gold' => 10000,
            'platinum' => null, // Already at highest tier
            default => 2000,
        };
    }

    /**
     * Calculate potential points for a purchase amount
     */
    public function calculatePotentialPoints(float $amount, string $tier = 'bronze'): int
    {
        $basePoints = (int) $amount;
        $tierMultiplier = $this->getTierMultiplier($tier);
        
        return (int) ($basePoints * $tierMultiplier);
    }
}
