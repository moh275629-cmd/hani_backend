<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Create a new notification for a user
     */
    public function createNotification(User $user, string $type, string $title, string $message, array $data = []): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'is_read' => false,
        ]);
    }

    /**
     * Create notification for points earned
     */
    public function notifyPointsEarned(User $user, int $points, float $amount): void
    {
        $this->createNotification(
            $user,
            'points_earned',
            'Points Earned!',
            "You earned {$points} points for your purchase of SAR " . number_format($amount, 2),
            [
                'points_earned' => $points,
                'purchase_amount' => $amount,
                'new_balance' => $user->loyaltyCard->points_balance ?? 0,
            ]
        );
    }

    /**
     * Create notification for tier upgrade
     */
    public function notifyTierUpgrade(User $user, string $newTier): void
    {
        $this->createNotification(
            $user,
            'tier_upgrade',
            'Tier Upgraded!',
            "Congratulations! You've been upgraded to {$newTier} tier!",
            [
                'new_tier' => $newTier,
                'benefits' => $this->getTierBenefits($newTier),
            ]
        );
    }

    /**
     * Create notification for offer redemption
     */
    public function notifyOfferRedemption(User $user, string $offerTitle, int $pointsUsed): void
    {
        $this->createNotification(
            $user,
            'offer_redemption',
            'Offer Redeemed!',
            "You successfully redeemed '{$offerTitle}' for {$pointsUsed} points",
            [
                'offer_title' => $offerTitle,
                'points_used' => $pointsUsed,
                'remaining_points' => $user->loyaltyCard->points_balance ?? 0,
            ]
        );
    }

    /**
     * Create notification for new offers
     */
    public function notifyNewOffers(User $user, array $offers): void
    {
        foreach ($offers as $offer) {
            $this->createNotification(
                $user,
                'new_offer',
                'New Offer Available!',
                "Check out our new offer: {$offer['title']}",
                [
                    'offer_id' => $offer['id'],
                    'offer_title' => $offer['title'],
                    'discount_value' => $offer['discount_value'],
                    'discount_type' => $offer['discount_type'],
                ]
            );
        }
    }

    /**
     * Create notification for nearby stores
     */
    public function notifyNearbyStores(User $user, array $stores): void
    {
        if (empty($stores)) {
            return;
        }

        $storeNames = array_slice(array_column($stores, 'name'), 0, 3);
        $storeList = implode(', ', $storeNames);
        
        $this->createNotification(
            $user,
            'nearby_stores',
            'Stores Near You',
            "Discover these stores near you: {$storeList}",
            [
                'stores' => $stores,
                'count' => count($stores),
            ]
        );
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Notification $notification): void
    {
        $notification->update(['is_read' => true]);
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    /**
     * Get unread notifications count for a user
     */
    public function getUnreadCount(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Clean up old notifications (older than 90 days)
     */
    public function cleanupOldNotifications(): int
    {
        $cutoffDate = now()->subDays(90);
        
        return Notification::where('created_at', '<', $cutoffDate)
            ->delete();
    }

    /**
     * Get tier benefits for notification
     */
    private function getTierBenefits(string $tier): array
    {
        return match ($tier) {
            'silver' => [
                '20% more points on purchases',
                'Exclusive silver member offers',
                'Priority customer support',
            ],
            'gold' => [
                '50% more points on purchases',
                'Exclusive gold member offers',
                'Priority customer support',
                'Birthday rewards',
            ],
            'platinum' => [
                '100% more points on purchases',
                'Exclusive platinum member offers',
                'VIP customer support',
                'Birthday rewards',
                'Free delivery on all purchases',
            ],
            default => [
                'Standard points earning',
                'Access to basic offers',
            ],
        };
    }

    /**
     * Send push notification (placeholder for future implementation)
     */
    public function sendPushNotification(User $user, string $title, string $message): void
    {
        // This would integrate with Firebase Cloud Messaging or similar service
        Log::info("Push notification sent to user {$user->id}: {$title} - {$message}");
    }

    /**
     * Send email notification (placeholder for future implementation)
     */
    public function sendEmailNotification(User $user, string $subject, string $content): void
    {
        // This would integrate with Laravel's mail system
        Log::info("Email notification sent to user {$user->id}: {$subject}");
    }
}
