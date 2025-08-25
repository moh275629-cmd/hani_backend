<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Branch;
use App\Models\Offer;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class StoreService
{
    /**
     * Find stores near a specific location
     */
    public function findNearbyStores(float $latitude, float $longitude, float $radius = 10.0, int $limit = 20): Collection
    {
        $stores = Store::select('stores.*')
            ->selectRaw('
                (6371 * acos(cos(radians(?)) * cos(radians(branches.latitude)) * 
                cos(radians(branches.longitude) - radians(?)) + 
                sin(radians(?)) * sin(radians(branches.latitude)))) AS distance
            ', [$latitude, $longitude, $latitude])
            ->join('branches', 'stores.id', '=', 'branches.store_id')
            ->where('stores.is_active', true)
            ->where('branches.is_active', true)
            ->having('distance', '<=', $radius)
            ->orderBy('distance')
            ->limit($limit)
            ->get();

        return $stores;
    }

    /**
     * Search stores by name, category, or location
     */
    public function searchStores(string $query, ?string $category = null, ?float $latitude = null, ?float $longitude = null): Collection
    {
        $stores = Store::query()
            ->where('is_active', true)
            ->searchByEncryptedFields($query);

        if ($category) {
            $stores->where('category', $category);
        }

        if ($latitude && $longitude) {
            $stores->select('stores.*')
                ->selectRaw('
                    (6371 * acos(cos(radians(?)) * cos(radians(branches.latitude)) * 
                    cos(radians(branches.longitude) - radians(?)) + 
                    sin(radians(?)) * sin(radians(branches.latitude)))) AS distance
                ', [$latitude, $longitude, $latitude])
                ->join('branches', 'stores.id', '=', 'branches.store_id')
                ->orderBy('distance');
        }

        return $stores->with(['branches', 'offers' => function ($q) {
            $q->where('is_active', true);
        }])->get();
    }

    /**
     * Get store categories with counts
     */
    public function getStoreCategories(): array
    {
        return Store::select('category', DB::raw('count(*) as count'))
            ->where('is_active', true)
            ->groupBy('category')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get store statistics
     */
    public function getStoreStats(int $storeId): array
    {
        $store = Store::findOrFail($storeId);
        
        $totalBranches = $store->branches()->where('is_active', true)->count();
        $totalOffers = $store->offers()->where('is_active', true)->count();
        
        // Get recent purchases
        $recentPurchases = DB::table('purchases')
            ->where('store_id', $storeId)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        // Get total revenue
        $totalRevenue = DB::table('purchases')
            ->where('store_id', $storeId)
            ->sum('final_amount');

        // Get average purchase amount
        $avgPurchase = DB::table('purchases')
            ->where('store_id', $storeId)
            ->avg('final_amount');

        return [
            'store_id' => $storeId,
            'store_name' => $store->name,
            'total_branches' => $totalBranches,
            'total_offers' => $totalOffers,
            'recent_purchases_30d' => $recentPurchases,
            'total_revenue' => $totalRevenue,
            'average_purchase' => $avgPurchase,
        ];
    }

    /**
     * Get store analytics for admin dashboard
     */
    public function getStoreAnalytics(int $storeId, string $period = '30d'): array
    {
        $days = match ($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 30,
        };

        $startDate = now()->subDays($days);

        // Daily purchases
        $dailyPurchases = DB::table('purchases')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'), DB::raw('sum(final_amount) as revenue'))
            ->where('store_id', $storeId)
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top products/services
        $topProducts = DB::table('purchases')
            ->select('product_name', DB::raw('count(*) as count'), DB::raw('sum(final_amount) as revenue'))
            ->where('store_id', $storeId)
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('product_name')
            ->groupBy('product_name')
            ->orderBy('revenue', 'desc')
            ->limit(10)
            ->get();

        // Customer loyalty stats
        $loyaltyStats = DB::table('purchases as p')
            ->select(
                DB::raw('count(distinct p.user_id) as unique_customers'),
                DB::raw('avg(p.final_amount) as avg_purchase'),
                DB::raw('count(*) / count(distinct p.user_id) as purchases_per_customer')
            )
            ->where('p.store_id', $storeId)
            ->where('p.created_at', '>=', $startDate)
            ->first();

        return [
            'period' => $period,
            'daily_purchases' => $dailyPurchases,
            'top_products' => $topProducts,
            'loyalty_stats' => $loyaltyStats,
        ];
    }

    /**
     * Get store recommendations for a user
     */
    public function getStoreRecommendations(int $userId, ?float $latitude = null, ?float $longitude = null): Collection
    {
        // Get user's purchase history
        $userStores = DB::table('purchases')
            ->select('store_id', DB::raw('count(*) as visit_count'))
            ->where('user_id', $userId)
            ->groupBy('store_id')
            ->orderBy('visit_count', 'desc')
            ->limit(5)
            ->pluck('store_id')
            ->toArray();

        $recommendations = Store::where('is_active', true)
            ->whereIn('id', $userStores)
            ->with(['branches', 'offers' => function ($q) {
                $q->where('is_active', true);
            }])
            ->get();

        // If location is provided, add nearby stores
        if ($latitude && $longitude) {
            $nearbyStores = $this->findNearbyStores($latitude, $longitude, 5.0, 10);
            $recommendations = $recommendations->merge($nearbyStores);
        }

        return $recommendations->unique('id');
    }

    /**
     * Get store operating hours for a specific branch
     */
    public function getBranchOperatingHours(int $branchId): array
    {
        $branch = Branch::findOrFail($branchId);
        
        // This would typically come from a separate operating_hours table
        // For now, returning default hours
        return [
            'monday' => ['09:00', '18:00'],
            'tuesday' => ['09:00', '18:00'],
            'wednesday' => ['09:00', '18:00'],
            'thursday' => ['09:00', '18:00'],
            'friday' => ['09:00', '18:00'],
            'saturday' => ['10:00', '16:00'],
            'sunday' => ['10:00', '16:00'],
        ];
    }

    /**
     * Check if a store branch is currently open
     */
    public function isBranchOpen(int $branchId): bool
    {
        $branch = Branch::findOrFail($branchId);
        
        if (!$branch->is_active) {
            return false;
        }

        $now = now();
        $dayOfWeek = strtolower($now->format('l'));
        $currentTime = $now->format('H:i');
        
        $operatingHours = $this->getBranchOperatingHours($branchId);
        
        if (!isset($operatingHours[$dayOfWeek])) {
            return false;
        }
        
        [$openTime, $closeTime] = $operatingHours[$dayOfWeek];
        
        return $currentTime >= $openTime && $currentTime <= $closeTime;
    }
}
