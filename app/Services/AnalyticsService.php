<?php

namespace App\Services;

use App\Models\User;
use App\Models\Store;
use App\Models\Purchase;
use App\Models\Offer;
use App\Models\LoyaltyCard;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Get overall system analytics
     */
    public function getSystemAnalytics(string $period = '30d'): array
    {
        $days = $this->getDaysFromPeriod($period);
        $startDate = now()->subDays($days);

        // User statistics
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $newUsers = User::where('created_at', '>=', $startDate)->count();
        
        // Store statistics
        $totalStores = Store::count();
        $activeStores = Store::where('is_active', true)->count();
        
        // Purchase statistics
        $totalPurchases = Purchase::where('created_at', '>=', $startDate)->count();
        $totalRevenue = Purchase::where('created_at', '>=', $startDate)->sum('final_amount');
        $avgPurchaseAmount = Purchase::where('created_at', '>=', $startDate)->avg('final_amount');
        
        // Loyalty statistics
        $totalLoyaltyCards = LoyaltyCard::count();
        $activeLoyaltyCards = LoyaltyCard::where('is_active', true)->count();
        $totalPointsIssued = LoyaltyCard::sum('points_balance');
        
        // Offer statistics
        $totalOffers = Offer::count();
        $activeOffers = Offer::where('is_active', true)->count();
        $totalRedemptions = Offer::sum('current_redemptions');

        return [
            'period' => $period,
            'users' => [
                'total' => $totalUsers,
                'active' => $activeUsers,
                'new' => $newUsers,
                'growth_rate' => $this->calculateGrowthRate('users', $period),
            ],
            'stores' => [
                'total' => $totalStores,
                'active' => $activeStores,
                'growth_rate' => $this->calculateGrowthRate('stores', $period),
            ],
            'purchases' => [
                'total' => $totalPurchases,
                'revenue' => $totalRevenue,
                'average_amount' => $avgPurchaseAmount,
                'growth_rate' => $this->calculateGrowthRate('purchases', $period),
            ],
            'loyalty' => [
                'total_cards' => $totalLoyaltyCards,
                'active_cards' => $activeLoyaltyCards,
                'total_points' => $totalPointsIssued,
                'growth_rate' => $this->calculateGrowthRate('loyalty_cards', $period),
            ],
            'offers' => [
                'total' => $totalOffers,
                'active' => $activeOffers,
                'redemptions' => $totalRedemptions,
                'redemption_rate' => $totalOffers > 0 ? ($totalRedemptions / $totalOffers) * 100 : 0,
            ],
        ];
    }

    /**
     * Get user engagement analytics
     */
    public function getUserEngagementAnalytics(string $period = '30d'): array
    {
        $days = $this->getDaysFromPeriod($period);
        $startDate = now()->subDays($days);

        // Daily active users
        $dailyActiveUsers = DB::table('purchases')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(distinct user_id) as unique_users'))
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // User retention
        $retentionData = $this->calculateUserRetention($startDate);

        // User segments by activity
        $userSegments = $this->getUserSegments();

        // Top users by points
        $topUsers = LoyaltyCard::with('user')
            ->orderBy('points_balance', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($card) {
                return [
                    'user_id' => $card->user_id,
                    'name' => $card->user->name,
                    'points' => $card->points_balance,
                    'tier' => $card->tier,
                ];
            });

        return [
            'period' => $period,
            'daily_active_users' => $dailyActiveUsers,
            'retention' => $retentionData,
            'user_segments' => $userSegments,
            'top_users' => $topUsers,
        ];
    }

    /**
     * Get store performance analytics
     */
    public function getStorePerformanceAnalytics(string $period = '30d'): array
    {
        $days = $this->getDaysFromPeriod($period);
        $startDate = now()->subDays($days);

        // Top performing stores
        $topStores = DB::table('purchases as p')
            ->join('stores as s', 'p.store_id', '=', 's.id')
            ->select(
                's.id',
                's.name',
                's.category',
                DB::raw('count(*) as purchase_count'),
                DB::raw('sum(p.final_amount) as total_revenue'),
                DB::raw('avg(p.final_amount) as avg_purchase'),
                DB::raw('count(distinct p.user_id) as unique_customers')
            )
            ->where('p.created_at', '>=', $startDate)
            ->groupBy('s.id', 's.name', 's.category')
            ->orderBy('total_revenue', 'desc')
            ->limit(20)
            ->get();

        // Store category performance
        $categoryPerformance = DB::table('purchases as p')
            ->join('stores as s', 'p.store_id', '=', 's.id')
            ->select(
                's.category',
                DB::raw('count(*) as purchase_count'),
                DB::raw('sum(p.final_amount) as total_revenue'),
                DB::raw('avg(p.final_amount) as avg_purchase')
            )
            ->where('p.created_at', '>=', $startDate)
            ->groupBy('s.category')
            ->orderBy('total_revenue', 'desc')
            ->get();

        return [
            'period' => $period,
            'top_stores' => $topStores,
            'category_performance' => $categoryPerformance,
        ];
    }

    /**
     * Get loyalty program analytics
     */
    public function getLoyaltyProgramAnalytics(string $period = '30d'): array
    {
        $days = $this->getDaysFromPeriod($period);
        $startDate = now()->subDays($days);

        // Tier distribution
        $tierDistribution = LoyaltyCard::select('tier', DB::raw('count(*) as count'))
            ->groupBy('tier')
            ->orderBy('count', 'desc')
            ->get();

        // Points distribution
        $pointsDistribution = $this->getPointsDistribution();

        // Tier upgrade trends
        $tierUpgrades = $this->getTierUpgradeTrends($startDate);

        // Points earning vs spending
        $pointsAnalytics = $this->getPointsAnalytics($startDate);

        return [
            'period' => $period,
            'tier_distribution' => $tierDistribution,
            'points_distribution' => $pointsDistribution,
            'tier_upgrades' => $tierUpgrades,
            'points_analytics' => $pointsAnalytics,
        ];
    }

    /**
     * Get offer performance analytics
     */
    public function getOfferPerformanceAnalytics(string $period = '30d'): array
    {
        $days = $this->getDaysFromPeriod($period);
        $startDate = now()->subDays($days);

        // Top performing offers
        $topOffers = Offer::select(
            'id',
            'title',
            'store_id',
            'discount_type',
            'discount_value',
            'points_required',
            'current_redemptions',
            'max_redemptions'
        )
        ->where('created_at', '>=', $startDate)
        ->orderBy('current_redemptions', 'desc')
        ->limit(20)
        ->get();

        // Offer redemption trends
        $redemptionTrends = $this->getOfferRedemptionTrends($startDate);

        // Store offer performance
        $storeOfferPerformance = $this->getStoreOfferPerformance($startDate);

        return [
            'period' => $period,
            'top_offers' => $topOffers,
            'redemption_trends' => $redemptionTrends,
            'store_performance' => $storeOfferPerformance,
        ];
    }

    /**
     * Get revenue analytics
     */
    public function getRevenueAnalytics(string $period = '30d'): array
    {
        $days = $this->getDaysFromPeriod($period);
        $startDate = now()->subDays($days);

        // Daily revenue
        $dailyRevenue = DB::table('purchases')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('sum(final_amount) as revenue'),
                DB::raw('count(*) as transactions')
            )
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Revenue by store category
        $revenueByCategory = DB::table('purchases as p')
            ->join('stores as s', 'p.store_id', '=', 's.id')
            ->select(
                's.category',
                DB::raw('sum(p.final_amount) as revenue'),
                DB::raw('count(*) as transactions')
            )
            ->where('p.created_at', '>=', $startDate)
            ->groupBy('s.category')
            ->orderBy('revenue', 'desc')
            ->get();

        // Average order value trends
        $aovTrends = $this->getAverageOrderValueTrends($startDate);

        return [
            'period' => $period,
            'daily_revenue' => $dailyRevenue,
            'revenue_by_category' => $revenueByCategory,
            'aov_trends' => $aovTrends,
        ];
    }

    /**
     * Helper methods
     */
    private function getDaysFromPeriod(string $period): int
    {
        return match ($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '180d' => 180,
            '365d' => 365,
            default => 30,
        };
    }

    private function calculateGrowthRate(string $table, string $period): float
    {
        $days = $this->getDaysFromPeriod($period);
        $currentPeriod = DB::table($table)->where('created_at', '>=', now()->subDays($days))->count();
        $previousPeriod = DB::table($table)->whereBetween('created_at', [
            now()->subDays($days * 2),
            now()->subDays($days)
        ])->count();

        if ($previousPeriod == 0) {
            return $currentPeriod > 0 ? 100 : 0;
        }

        return round((($currentPeriod - $previousPeriod) / $previousPeriod) * 100, 2);
    }

    private function calculateUserRetention(Carbon $startDate): array
    {
        // Simplified retention calculation
        $totalUsers = User::where('created_at', '<', $startDate)->count();
        $activeUsers = DB::table('purchases')
            ->where('created_at', '>=', $startDate)
            ->distinct('user_id')
            ->count('user_id');

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'retention_rate' => $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 2) : 0,
        ];
    }

    private function getUserSegments(): array
    {
        return [
            'high_value' => LoyaltyCard::where('points_balance', '>=', 5000)->count(),
            'medium_value' => LoyaltyCard::whereBetween('points_balance', [1000, 4999])->count(),
            'low_value' => LoyaltyCard::where('points_balance', '<', 1000)->count(),
        ];
    }

    private function getPointsDistribution(): array
    {
        return [
            '0-500' => LoyaltyCard::whereBetween('points_balance', [0, 500])->count(),
            '501-1000' => LoyaltyCard::whereBetween('points_balance', [501, 1000])->count(),
            '1001-2500' => LoyaltyCard::whereBetween('points_balance', [1001, 2500])->count(),
            '2501-5000' => LoyaltyCard::whereBetween('points_balance', [2501, 5000])->count(),
            '5000+' => LoyaltyCard::where('points_balance', '>', 5000)->count(),
        ];
    }

    private function getTierUpgradeTrends(Carbon $startDate): array
    {
        // This would require tracking tier changes in a separate table
        // For now, returning placeholder data
        return [
            'bronze_to_silver' => 0,
            'silver_to_gold' => 0,
            'gold_to_platinum' => 0,
        ];
    }

    private function getPointsAnalytics(Carbon $startDate): array
    {
        // This would require tracking points earned vs spent
        // For now, returning placeholder data
        return [
            'points_earned' => 0,
            'points_spent' => 0,
            'net_points' => 0,
        ];
    }

    private function getOfferRedemptionTrends(Carbon $startDate): array
    {
        // This would require tracking offer redemptions over time
        // For now, returning placeholder data
        return [
            'daily_redemptions' => [],
            'redemption_rate' => 0,
        ];
    }

    private function getStoreOfferPerformance(Carbon $startDate): array
    {
        // This would require tracking offer performance by store
        // For now, returning placeholder data
        return [
            'store_performance' => [],
        ];
    }

    private function getAverageOrderValueTrends(Carbon $startDate): array
    {
        // This would calculate AOV trends over time
        // For now, returning placeholder data
        return [
            'daily_aov' => [],
            'trend' => 'stable',
        ];
    }
}
