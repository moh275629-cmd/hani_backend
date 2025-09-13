<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\User;
use App\Models\Store;
use App\Models\Branch;
use App\Models\Offer;
use App\Models\Purchase;
use App\Models\LoyaltyCard;
use App\Models\Notification;
use App\Models\Report;
use App\Models\Activation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    public function index()
    {
        $admins = Admin::with(['user', 'wilaya'])->paginate(50);
        return response()->json(['success' => true, 'data' => $admins]);
    }

    public function show($id)
    {
        $admin = Admin::with(['user', 'wilaya'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $admin]);
    }

    public function store(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'user_id' => 'required|exists:users,id|unique:admins,user_id',
            'wilaya_code' => 'nullable|exists:wilayas,code',
            'office_address' => 'nullable|string|max:500',
            'office_location_lat' => 'nullable|numeric|between:-90,90',
            'office_location_lng' => 'nullable|numeric|between:-180,180',
            'office_phone' => 'nullable|string|max:50',
            'office_email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Ensure user has role admin
        $user = User::findOrFail($data['user_id']);
        if ($user->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'User is not admin role'], 422);
        }

        $admin = Admin::create($validator->validated());
        return response()->json(['success' => true, 'data' => $admin], 201);
    }

    public function update(Request $request, $id)
    {
        $admin = Admin::findOrFail($id);
        $data = $request->all();
        $validator = Validator::make($data, [
            'wilaya_code' => 'nullable|exists:wilayas,code',
            'office_address' => 'nullable|string|max:500',
            'office_location_lat' => 'nullable|numeric|between:-90,90',
            'office_location_lng' => 'nullable|numeric|between:-180,180',
            'office_phone' => 'nullable|string|max:50',
            'office_email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $admin->update($validator->validated());
        return response()->json(['success' => true, 'data' => $admin]);
    }

    public function destroy($id)
    {
        $admin = Admin::findOrFail($id);
        $admin->delete();
        return response()->json(['success' => true]);
    }
    /**
     * Get admin dashboard statistics
     */
    public function dashboard(): JsonResponse
    {
        try {
            $stats = [
                'total_users' => User::count(),
                'active_users' => User::where('is_active', true)->count(),
                'total_stores' => Store::count(),
                'approved_stores' => Store::where('is_approved', true)->count(),
                'pending_stores' => Store::where('is_approved', false)->count(),
                'total_offers' => Offer::count(),
                'active_offers' => Offer::where('is_active', true)->count(),
                'total_purchases' => Purchase::count(),
                'total_revenue' => Purchase::sum('final_amount'),
                'total_points_issued' => LoyaltyCard::sum('points'),
            ];

            // Monthly statistics for the last 6 months
            $monthlyStats = [];
            for ($i = 5; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $monthlyStats[] = [
                    'month' => $date->format('M Y'),
                    'users' => User::whereMonth('created_at', $date->month)
                        ->whereYear('created_at', $date->year)->count(),
                    'purchases' => Purchase::whereMonth('purchase_date', $date->month)
                        ->whereYear('purchase_date', $date->year)->count(),
                    'revenue' => Purchase::whereMonth('purchase_date', $date->month)
                        ->whereYear('purchase_date', $date->year)->sum('final_amount'),
                ];
            }

            return response()->json([
                'message' => 'Dashboard statistics retrieved successfully',
                'data' => [
                    'overview' => $stats,
                    'monthly_trends' => $monthlyStats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all users with pagination and filters
     */
    public function users(Request $request): JsonResponse
    {
        try {
            $query = User::with(['loyaltyCards', 'stores']);
            $currentUser = auth()->user();

            // Apply filters
            if ($request->has('search')) {
                $search = $request->search;
                $query->searchByEncryptedFields($search);
            }

            if ($request->has('role')) {
                $query->where('role', $request->role);
            }

            if ($request->has('status')) {
                $query->where('is_active', $request->status === 'active');
            }

            // Get all users first (since state is encrypted, we need to filter after decryption)
            $allUsers = $query->get();
            
            // Apply role-based filtering after decryption
            if ($currentUser->isAdmin() && !$currentUser->isGlobalAdmin()) {
                // Regional admin can only see users from their wilaya
                $adminWilayaCode = null;
                
                // Get the admin's wilaya code from the admins table
                $admin = \App\Models\Admin::where('user_id', $currentUser->id)->first();
                if ($admin) {
                    $adminWilayaCode = $admin->wilaya_code;
                }
                
                if ($adminWilayaCode) {
                    $allUsers = $allUsers->filter(function ($user) use ($adminWilayaCode) {
                        // Compare with user's state (which should contain wilaya code)
                        return $user->state === $adminWilayaCode;
                    });
                } else {
                    // If no wilaya code found, return empty result
                    $allUsers = collect();
                }
            }
            
            // Apply state filter if provided (for global admins)
            if ($request->has('state')) {
                $allUsers = $allUsers->filter(function ($user) use ($request) {
                    return $user->state === $request->state;
                });
            }
            
            // Apply pagination
            $perPage = $request->get('per_page', 15);
            $page = $request->get('page', 1);
            $total = $allUsers->count();
            $offset = ($page - 1) * $perPage;
            
            $paginatedUsers = $allUsers->slice($offset, $perPage);
            
            $data = [
                'data' => $paginatedUsers->values(),
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total),
            ];

            return response()->json([
                'message' => 'Users retrieved successfully',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user details
     */
    public function user(User $user): JsonResponse
    {
        try {
            $user->load(['loyaltyCards', 'stores', 'purchases', 'notifications']);

            return response()->json([
                'message' => 'User details retrieved successfully',
                'data' => $user
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving user details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user status
     */
    public function updateUserStatus(Request $request, $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_active' => 'required|boolean',
            'reason' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Resolve ID from route param to avoid any route-model binding issues
            $userId = is_numeric($user) ? (int) $user : (int) $request->route('user');
            if ($userId <= 0) {
                return response()->json([
                    'message' => 'Invalid user id',
                    'updated' => false,
                    'id' => null,
                    'db_connection' => config('database.default'),
                    'db_database' => (string) DB::connection()->getDatabaseName(),
                ], 400);
            }

            // Find the user directly
            $userModel = User::find($userId);
            if (!$userModel) {
                return response()->json([
                    'message' => 'User not found',
                    'updated' => false,
                    'id' => $userId,
                    'db_connection' => config('database.default'),
                    'db_database' => (string) DB::connection()->getDatabaseName(),
                ], 404);
            }

            $userModel->update([
                'is_active' => $request->is_active
            ]);

            // Send notification to user
            if ($request->has('reason')) {
                Notification::create([
                    'user_id' => $userModel->id,
                    'title' => $request->is_active ? 'Account Activated' : 'Account Suspended',
                    'message' => $request->reason,
                    // Use valid enum value for type and encode severity via priority
                    'type' => 'system',
                    'priority' => $request->is_active ? 'normal' : 'high',
                    'is_read' => false
                ]);
            }

            return response()->json([
                'message' => 'User status updated successfully',
                'data' => $userModel,
                'updated' => true,
                'id' => $userId,
                'db_connection' => config('database.default'),
                'db_database' => (string) DB::connection()->getDatabaseName(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating user status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user profile fields (admin only subset)
     */
    public function updateUserProfile(Request $request, $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'state' => 'sometimes|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = is_numeric($user) ? (int) $user : (int) $request->route('user');
            if ($userId <= 0) {
                return response()->json([
                    'message' => 'Invalid user id',
                    'updated' => false,
                    'id' => null,
                    'db_connection' => config('database.default'),
                    'db_database' => (string) DB::connection()->getDatabaseName(),
                ], 400);
            }

            $userModel = User::find($userId);
            if (!$userModel) {
                return response()->json([
                    'message' => 'User not found',
                    'updated' => false,
                    'id' => $userId,
                    'db_connection' => config('database.default'),
                    'db_database' => (string) DB::connection()->getDatabaseName(),
                ], 404);
            }

            $payload = [];
            if ($request->has('state')) {
                $payload['state'] = $request->state;
            }


            if (!empty($payload)) {
                $userModel->update($payload);
            }

            return response()->json([
                'message' => 'User profile updated successfully',
                'data' => $userModel,
                'updated' => true,
                'id' => $userId,
                'db_connection' => config('database.default'),
                'db_database' => (string) DB::connection()->getDatabaseName(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating user profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all stores with pagination and filters
     */
    public function stores(Request $request): JsonResponse
    {
        try {
            $query = Store::with(['owner']);
            $currentUser = auth()->user();

            // Apply filters
            if ($request->has('search')) {
                $search = $request->search;
                $query->searchByEncryptedFields($search);
            }

            if ($request->has('status')) {
                $query->where('is_approved', $request->status === 'approved');
            }

            if ($request->has('category')) {
                $query->where('business_type', $request->category);
            }

            // Get all stores first (since state is encrypted, we need to filter after decryption)
            $allStores = $query->get();
            
            // Debug: Log sample store states
            \Log::info('Sample store states in database', [
                'total_stores' => $allStores->count(),
                'sample_states' => $allStores->take(5)->map(function($store) {
                    return [
                        'store_id' => $store->id,
                        'state' => $store->state
                    ];
                })->toArray()
            ]);
            
            // Apply role-based filtering after decryption
            if ($currentUser->isAdmin() && !$currentUser->isGlobalAdmin()) {
                // Regional admin can only see stores from their wilaya
                $adminWilayaCode = null;
                
                // Get the admin's wilaya code from the admins table
                $admin = \App\Models\Admin::where('user_id', $currentUser->id)->first();
                if ($admin) {
                    $adminWilayaCode = $admin->wilaya_code;
                }
                
                if ($adminWilayaCode) {
                    $allStores = $allStores->filter(function ($store) use ($adminWilayaCode) {
                        // Compare with store's state_code (which should contain wilaya code)
                        return $store->state_code === $adminWilayaCode;
                    });
                } else {
                    // If no wilaya code found, return empty result
                    $allStores = collect();
                }
            }
            
            // Apply state filter if provided (for global admins)
            if ($request->has('state')) {
                \Log::info('Admin filtering stores by state', [
                    'requested_state' => $request->state,
                    'total_stores_before_filter' => $allStores->count()
                ]);
                
                $allStores = $allStores->filter(function ($store) use ($request) {
                    $matches = $store->state === $request->state;
                    if ($matches) {
                        \Log::info('Store matches state filter', [
                            'store_id' => $store->id,
                            'store_state' => $store->state,
                            'requested_state' => $request->state
                        ]);
                    }
                    return $matches;
                });
                
                \Log::info('State filtering results', [
                    'matching_stores' => $allStores->count(),
                    'requested_state' => $request->state
                ]);
            }
            
            // Apply pagination
            $perPage = $request->get('per_page', 15);
            $page = $request->get('page', 1);
            $total = $allStores->count();
            $offset = ($page - 1) * $perPage;
            
            $paginatedStores = $allStores->slice($offset, $perPage);
            
            $data = [
                'data' => $paginatedStores->values(),
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total),
            ];

            return response()->json([
                'message' => 'Stores retrieved successfully',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving stores',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve or reject store
     */
    public function updateStoreStatus(Request $request, $store): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_approved' => 'required|boolean',
            'reason' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Resolve ID from route param to avoid any route-model binding issues
            $storeId = is_numeric($store) ? (int) $store : (int) $request->route('store');
            if ($storeId <= 0) {
                return response()->json([
                    'message' => 'Invalid store id',
                    'updated' => false,
                    'id' => null,
                    'db_connection' => config('database.default'),
                    'db_database' => (string) DB::connection()->getDatabaseName(),
                ], 400);
            }

            // Find the store directly
            $storeModel = Store::find($storeId);
            if (!$storeModel) {
                return response()->json([
                    'message' => 'Store not found',
                    'updated' => false,
                    'id' => $storeId,
                    'db_connection' => config('database.default'),
                    'db_database' => (string) DB::connection()->getDatabaseName(),
                ], 404);
            }

            $storeModel->update([
                'is_approved' => $request->is_approved,
                'approved_at' => $request->is_approved ? now() : null
            ]);

            // Get the store owner
            $storeOwner = User::find($storeModel->user_id);

            // Send notification to store owner
            if ($request->has('reason')) {
                Notification::create([
                    // stores table references owner via user_id
                    'user_id' => $storeModel->user_id,
                    'title' => $request->is_approved ? 'Store Approved' : 'Store Rejected',
                    'message' => $request->reason,
                    'type' => 'system',
                    'priority' => $request->is_approved ? 'normal' : 'high',
                    'is_read' => false
                ]);
            }

            // Send email notification
            if ($storeOwner && $request->is_approved) {
                try {
                    Mail::to($storeOwner->email)->send(new \App\Mail\StoreApprovedMail($storeOwner, $storeModel));
                } catch (\Exception $e) {
                    \Log::error("Failed to send store approval email to: {$storeOwner->email}, Error: " . $e->getMessage());
                }
            }

            return response()->json([
                'message' => 'Store status updated successfully',
                'data' => $storeModel,
                'updated' => true,
                'id' => $storeId,
                'db_connection' => config('database.default'),
                'db_database' => (string) DB::connection()->getDatabaseName(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating store status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new store
     */
    public function createStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'store_name' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'business_type' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'website' => 'nullable|url|max:255',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'logo_url' => 'nullable|string',
            'banner_url' => 'nullable|string',
            'business_hours' => 'nullable|array',
            'payment_methods' => 'nullable|array',
            'services' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Build payload and normalize to match DB schema requirements
            $payload = [
                'user_id' => auth()->id(),
                'store_name' => $request->store_name,
                'description' => $request->description,
                'business_type' => $request->business_type,
                'phone' => $request->phone,
                'email' => $request->email,
                'website' => $request->website,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                // DB columns are NOT NULL for postal_code and country; default to empty string if omitted
                'postal_code' => $request->postal_code ?? '',
                'country' => $request->country ?? '',
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                // Map UI fields to DB columns
                'logo' => $request->logo_url,
                'banner' => $request->banner_url,
                // Default required JSON fields to empty arrays if not provided
                'business_hours' => $request->get('business_hours', []),
                'payment_methods' => $request->get('payment_methods', []),
                'services' => $request->get('services', []),
                'is_approved' => true, // Admin-created stores are auto-approved
                'is_active' => true,
                'approved_at' => now(),
                'approved_by' => auth()->id(),
            ];

            $store = Store::create($payload);

            // Send approval email to store owner since admin-created stores are auto-approved
            $storeOwner = User::find($store->user_id);
            if ($storeOwner) {
                try {
                    Mail::to($storeOwner->email)->send(new \App\Mail\StoreApprovedMail($storeOwner, $store));
                } catch (\Exception $e) {
                    \Log::error("Failed to send store approval email to: {$storeOwner->email}, Error: " . $e->getMessage());
                }
            }

            return response()->json([
                'message' => 'Store created successfully',
                'data' => $store
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating store',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update store
     */
    public function updateStore(Request $request, $store): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'store_name' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'business_type' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'website' => 'nullable|url|max:255',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'logo_url' => 'nullable|string',
            'banner_url' => 'nullable|string',
            'business_hours' => 'nullable|array',
            'payment_methods' => 'nullable|array',
            'services' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Resolve ID from route param to avoid any route-model binding issues
            $storeId = is_numeric($store) ? (int) $store : (int) $request->route('store');
            if ($storeId <= 0) {
                return response()->json([
                    'message' => 'Invalid store id',
                    'updated' => false,
                    'id' => null,
                    'db_connection' => config('database.default'),
                    'db_database' => (string) DB::connection()->getDatabaseName(),
                ], 400);
            }

            // Find the store directly
            $storeModel = Store::find($storeId);
            if (!$storeModel) {
                return response()->json([
                    'message' => 'Store not found',
                    'updated' => false,
                    'id' => $storeId,
                    'db_connection' => config('database.default'),
                    'db_database' => (string) DB::connection()->getDatabaseName(),
                ], 404);
            }

            // Build update payload; avoid setting NOT NULL columns to null
            $payload = [
                'store_name' => $request->store_name,
                'description' => $request->description,
                'business_type' => $request->business_type,
                'phone' => $request->phone,
                'email' => $request->email,
                'website' => $request->website,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
            ];

            // Only include NOT NULL columns if a non-null value is provided
            if ($request->has('postal_code') && $request->postal_code !== null) {
                $payload['postal_code'] = $request->postal_code;
            }
            if ($request->has('country') && $request->country !== null) {
                $payload['country'] = $request->country;
            }

            // Map UI image fields if present
            if ($request->has('logo_url')) {
                $payload['logo'] = $request->logo_url;
            }
            if ($request->has('banner_url')) {
                $payload['banner'] = $request->banner_url;
            }

            // Optional JSON fields
            if ($request->has('business_hours')) {
                $payload['business_hours'] = $request->business_hours ?? [];
            }
            if ($request->has('payment_methods')) {
                $payload['payment_methods'] = $request->payment_methods ?? [];
            }
            if ($request->has('services')) {
                $payload['services'] = $request->services ?? [];
            }

            $storeModel->update($payload);

            return response()->json([
                'message' => 'Store updated successfully',
                'data' => $storeModel,
                'updated' => true,
                'id' => $storeId,
                'db_connection' => config('database.default'),
                'db_database' => (string) DB::connection()->getDatabaseName(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating store',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete store
     */
    public function deleteStore(Request $request, $store): JsonResponse
    {
        try {
            // Resolve ID from route param to avoid any route-model binding issues
            $storeId = is_numeric($store) ? (int) $store : (int) $request->route('store');
            if ($storeId <= 0) {
                return response()->json([
                    'message' => 'Invalid store id',
                    'deleted' => 0,
                    'id' => null,
                    'db_connection' => config('database.default'),
                    'db_database' => (string) DB::connection()->getDatabaseName(),
                ], 400);
            }

            // Perform a direct delete and return affected rows for robustness
            $deletedRows = Store::where('id', $storeId)->delete();

            return response()->json([
                'message' => $deletedRows === 1 ? 'Store deleted successfully' : 'Store not found or already deleted',
                'deleted' => (int) $deletedRows,
                'id' => $storeId,
                'db_connection' => config('database.default'),
                'db_database' => (string) DB::connection()->getDatabaseName(),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting store',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle store approval status
     */
    public function toggleStoreApproval(Request $request, $store): JsonResponse
    {
        try {
            // Resolve ID from route param to avoid any route-model binding issues
            $storeId = is_numeric($store) ? (int) $store : (int) $request->route('store');
            if ($storeId <= 0) {
                return response()->json([
                    'message' => 'Invalid store id',
                    'updated' => false,
                    'id' => null,
                    'db_connection' => config('database.default'),
                    'db_database' => (string) DB::connection()->getDatabaseName(),
                ], 400);
            }

            // Find the store directly
            $storeModel = Store::find($storeId);
            if (!$storeModel) {
                return response()->json([
                    'message' => 'Store not found',
                    'updated' => false,
                    'id' => $storeId,
                    'db_connection' => config('database.default'),
                    'db_database' => (string) DB::connection()->getDatabaseName(),
                ], 404);
            }

            $wasApproved = $storeModel->is_approved;
            $storeModel->update([
                'is_approved' => !$storeModel->is_approved,
                'approved_at' => !$storeModel->is_approved ? now() : null,
                'approved_by' => !$storeModel->is_approved ? auth()->id() : null,
            ]);

            // Send email notification if store was just approved
            if (!$wasApproved && $storeModel->is_approved) {
                $storeOwner = User::find($storeModel->user_id);
                if ($storeOwner) {
                    try {
                        Mail::to($storeOwner->email)->send(new \App\Mail\StoreApprovedMail($storeOwner, $storeModel));
                    } catch (\Exception $e) {
                        \Log::error("Failed to send store approval email to: {$storeOwner->email}, Error: " . $e->getMessage());
                    }
                }
            }

            return response()->json([
                'message' => $storeModel->is_approved ? 'Store approved successfully' : 'Store disapproved successfully',
                'data' => $storeModel,
                'updated' => true,
                'id' => $storeId,
                'db_connection' => config('database.default'),
                'db_database' => (string) DB::connection()->getDatabaseName(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error toggling store approval',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all offers with pagination and filters
     */
    public function offers(Request $request): JsonResponse
    {
        try {
            $query = Offer::with(['store', 'store.owner']);

            $currentUser = auth()->user();
            // Regional admin: restrict offers to stores from their wilaya
            if ($currentUser->isAdmin() && !$currentUser->isGlobalAdmin()) {
                // Get the admin's wilaya code from the admins table
                $admin = \App\Models\Admin::where('user_id', $currentUser->id)->first();
                if ($admin && $admin->wilaya_code) {
                    $query->whereHas('store', function($q) use ($admin) {
                        $q->where('state_code', $admin->wilaya_code);
                    });
                } else {
                    // If no wilaya code found, return empty result
                    $query->where('id', 0); // This will return no results
                }
            }

            // Apply filters
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($request->has('status')) {
                $query->where('is_active', $request->status === 'active');
            }

            if ($request->has('store_id')) {
                $query->where('store_id', $request->store_id);
            }

            $offers = $query->paginate($request->get('per_page', 15));

            // Ensure blob fields are not included in the response
            $offers->getCollection()->transform(function ($offer) {
                $offer->makeHidden(['image_blob']);
                if ($offer->store) {
                    $offer->store->makeHidden(['logo_blob', 'banner_blob']);
                }
                return $offer;
            });

            return response()->json([
                'message' => 'Offers retrieved successfully',
                'data' => $offers
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving offers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new offer
     */
    public function createOffer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required|exists:stores,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            // Accept both fixed and fixed_amount for compatibility with DB enum
            'discount_type' => 'required|in:percentage,fixed,fixed_amount',
            'discount_value' => 'required|numeric|min:0',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after:valid_from',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $payload = $request->all();
            // Normalize fields to match DB schema
            if (isset($payload['discount_type']) && $payload['discount_type'] === 'fixed') {
                $payload['discount_type'] = 'fixed_amount';
            }
            if (isset($payload['min_purchase'])) {
                $payload['minimum_purchase'] = $payload['min_purchase'];
                unset($payload['min_purchase']);
            }
            // Ignore unknown keys that are not fillable
            $offer = Offer::create($payload);

            return response()->json([
                'message' => 'Offer created successfully',
                'data' => $offer
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating offer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update offer
     */
    public function updateOffer(Request $request, $offer): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'description' => 'string|max:1000',
            // Accept both fixed and fixed_amount for compatibility with DB enum
            'discount_type' => 'in:percentage,fixed,fixed_amount',
            'discount_value' => 'numeric|min:0',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'valid_from' => 'date',
            'valid_until' => 'date|after:valid_from',
            'is_active' => 'boolean'
            
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Resolve ID from route param to avoid any route-model binding issues
            $offerId = is_numeric($offer) ? (int) $offer : (int) $request->route('offer');
            if ($offerId <= 0) {
                return response()->json([
                    'message' => 'Invalid offer id',
                    'updated' => false,
                    'id' => null,
                    'db_connection' => config('database.default'),
                    'db_database' => (string) DB::connection()->getDatabaseName(),
                ], 400);
            }

            // Find the offer directly
            $offerModel = Offer::find($offerId);
            if (!$offerModel) {
                return response()->json([
                    'message' => 'Offer not found',
                    'updated' => false,
                    'id' => $offerId,
                    'db_connection' => config('database.default'),
                    'db_database' => (string) DB::connection()->getDatabaseName(),
                ], 404);
            }

            $payload = $request->all();
            if (isset($payload['discount_type']) && $payload['discount_type'] === 'fixed') {
                $payload['discount_type'] = 'fixed_amount';
            }
            if (isset($payload['min_purchase'])) {
                $payload['minimum_purchase'] = $payload['min_purchase'];
                unset($payload['min_purchase']);
            }
            $offerModel->update($payload);

            return response()->json([
                'message' => 'Offer updated successfully',
                'data' => $offerModel,
                'updated' => true,
                'id' => $offerId,
                'db_connection' => config('database.default'),
                'db_database' => (string) DB::connection()->getDatabaseName(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating offer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete offer
     */
    public function deleteOffer(Request $request, $offer): JsonResponse
    {
        try {
            // Resolve ID from route param to avoid any route-model binding issues
            $offerId = is_numeric($offer) ? (int) $offer : (int) $request->route('offer');
            if ($offerId <= 0) {
                return response()->json([
                    'message' => 'Invalid offer id',
                    'deleted' => 0,
                    'id' => null,
                    'db_connection' => config('database.default'),
                    'db_database' => (string) DB::connection()->getDatabaseName(),
                ], 400);
            }

            // Perform a direct delete and return affected rows for robustness
            $deletedRows = Offer::where('id', $offerId)->delete();

            return response()->json([
                'message' => $deletedRows === 1 ? 'Offer deleted successfully' : 'Offer not found or already deleted',
                'deleted' => (int) $deletedRows,
                'id' => $offerId,
                'db_connection' => config('database.default'),
                'db_database' => (string) DB::connection()->getDatabaseName(),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting offer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get analytics data
     */
    public function analytics(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', 'month'); // day, week, month, year
            
            $analytics = [
                'user_growth' => $this->getUserGrowth($period),
                'revenue_trends' => $this->getRevenueTrends($period),
                'top_stores' => $this->getTopStores($period),
                'popular_offers' => $this->getPopularOffers($period),
                'geographic_distribution' => $this->getGeographicDistribution(),
            ];

            return response()->json([
                'message' => 'Analytics data retrieved successfully',
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user growth data
     */
    private function getUserGrowth(string $period): array
    {
        $query = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date');

        switch ($period) {
            case 'week':
                $query->where('created_at', '>=', now()->subWeek());
                break;
            case 'month':
                $query->where('created_at', '>=', now()->subMonth());
                break;
            case 'year':
                $query->where('created_at', '>=', now()->subYear());
                break;
            default:
                $query->where('created_at', '>=', now()->subMonth());
        }

        return $query->get()->toArray();
    }

    /**
     * Get revenue trends
     */
    private function getRevenueTrends(string $period): array
    {
        $query = Purchase::selectRaw('DATE(purchase_date) as date, SUM(final_amount) as revenue')
            ->groupBy('date')
            ->orderBy('date');

        switch ($period) {
            case 'week':
                $query->where('purchase_date', '>=', now()->subWeek());
                break;
            case 'month':
                $query->where('purchase_date', '>=', now()->subMonth());
                break;
            case 'year':
                $query->where('purchase_date', '>=', now()->subYear());
                break;
            default:
                $query->where('purchase_date', '>=', now()->subMonth());
        }

        return $query->get()->toArray();
    }

    /**
     * Get top performing stores
     */
    private function getTopStores(string $period): array
    {
        $query = Store::selectRaw('stores.store_name, COUNT(purchases.id) as purchase_count, SUM(purchases.final_amount) as revenue')
            ->leftJoin('purchases', 'stores.id', '=', 'purchases.store_id')
            ->groupBy('stores.id', 'stores.store_name')
            ->orderBy('revenue', 'desc')
            ->limit(10);

        switch ($period) {
            case 'week':
                $query->where('purchases.purchase_date', '>=', now()->subWeek());
                break;
            case 'month':
                $query->where('purchases.purchase_date', '>=', now()->subMonth());
                break;
            case 'year':
                $query->where('purchases.purchase_date', '>=', now()->subYear());
                break;
            default:
                $query->where('purchases.purchase_date', '>=', now()->subMonth());
        }

        return $query->get()->toArray();
    }

    /**
     * Get popular offers
     */
    private function getPopularOffers(string $period): array
    {
        $query = Offer::selectRaw('offers.title, COUNT(purchases.id) as redemption_count')
            ->leftJoin('purchases', 'offers.id', '=', 'purchases.offer_id')
            ->groupBy('offers.id', 'offers.title')
            ->orderBy('redemption_count', 'desc')
            ->limit(10);

        switch ($period) {
            case 'week':
                $query->where('purchases.purchase_date', '>=', now()->subWeek());
                break;
            case 'month':
                $query->where('purchases.purchase_date', '>=', now()->subMonth());
                break;
            case 'year':
                $query->where('purchases.purchase_date', '>=', now()->subYear());
                break;
            default:
                $query->where('purchases.purchase_date', '>=', now()->subMonth());
        }

        return $query->get()->toArray();
    }

    /**
     * Get geographic distribution
     */
    private function getGeographicDistribution(): array
    {
        return Store::selectRaw('city, COUNT(*) as store_count')
            ->groupBy('city')
            ->orderBy('store_count', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Get reports for admin (filtered by wilaya for regional admins)
     */
    public function getReports(Request $request): JsonResponse
    {
        try {
            $currentUser = auth()->user();
            
            if (!$currentUser->isAdmin() && !$currentUser->isGlobalAdmin()) {
                return response()->json([
                    'message' => 'Unauthorized',
                    'error' => 'Only admins can access reports'
                ], 403);
            }

            // Get all reports first (since state is encrypted, we need to filter after decryption)
            $allReports = Report::with(['reporter', 'reportedUser', 'reportedUser.stores'])->get();
            
            // Apply role-based filtering after decryption
            if ($currentUser->isAdmin() && !$currentUser->isGlobalAdmin()) {
                // Regional admin can only see reports from users in their wilaya
                $adminWilayaCode = null;
                
                // Get the admin's wilaya code from the admins table
                $admin = \App\Models\Admin::where('user_id', $currentUser->id)->first();
                if ($admin) {
                    $adminWilayaCode = $admin->wilaya_code;
                }
                
                if ($adminWilayaCode) {
                    $allReports = $allReports->filter(function ($report) use ($adminWilayaCode) {
                        return $report->reportedUser && $report->reportedUser->state === $adminWilayaCode;
                    });
                } else {
                    // If no wilaya code found, return empty result
                    $allReports = collect();
                }
            }
            
            // Apply state filter for global admin if requested
            if ($currentUser->isGlobalAdmin() && $request->has('state') && $request->state !== 'all') {
                $allReports = $allReports->filter(function ($report) use ($request) {
                    return $report->reportedUser && $report->reportedUser->state === $request->state;
                });
            }
            
            // Apply filters
            if ($request->has('status')) {
                $allReports = $allReports->filter(function ($report) use ($request) {
                    return $report->status === $request->status;
                });
            }

            if ($request->has('date_from')) {
                $allReports = $allReports->filter(function ($report) use ($request) {
                    return $report->created_at >= $request->date_from;
                });
            }

            if ($request->has('date_to')) {
                $allReports = $allReports->filter(function ($report) use ($request) {
                    return $report->created_at <= $request->date_to;
                });
            }
            
            // Sort by created_at desc
            $allReports = $allReports->sortByDesc('created_at');
            
            // Apply pagination manually
            $perPage = $request->get('per_page', 20);
            $page = $request->get('page', 1);
            $total = $allReports->count();
            $offset = ($page - 1) * $perPage;
            
            $paginatedReports = $allReports->slice($offset, $perPage);
            
            // Transform the data to include store information
            $paginatedReports->transform(function ($report) {
                $reportData = $report->toArray();
                
                // Add store information if the reported user has stores
                if ($report->reportedUser && $report->reportedUser->stores->isNotEmpty()) {
                    $reportData['reported_store'] = $report->reportedUser->stores->first()->toArray();
                }
                
                return $reportData;
            });
            
            $data = [
                'data' => $paginatedReports->values(),
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total),
            ];

            return response()->json([
                'message' => 'Reports retrieved successfully',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get expired accounts for admin
     */
    public function getExpiredAccounts(Request $request): JsonResponse
    {
        try {
            $currentUser = auth()->user();
            
            if (!$currentUser->isAdmin() && !$currentUser->isGlobalAdmin()) {
                return response()->json([
                    'message' => 'Unauthorized',
                    'error' => 'Only admins can access expired accounts'
                ], 403);
            }

            // Get all expired activations first (since state is encrypted, we need to filter after decryption)
            $allActivations = Activation::with(['user'])->expired()->get();
            
            // Apply role-based filtering after decryption
            if ($currentUser->isAdmin() && !$currentUser->isGlobalAdmin()) {
                // Regional admin can only see activations from users in their state
                $allActivations = $allActivations->filter(function ($activation) use ($currentUser) {
                    return $activation->user && $activation->user->state === $currentUser->state;
                });
            }
            
            // Apply state filter for global admin if requested
            if ($currentUser->isGlobalAdmin() && $request->has('state') && $request->state !== 'all') {
                $allActivations = $allActivations->filter(function ($activation) use ($request) {
                    return $activation->user && $activation->user->state === $request->state;
                });
            }
            
            // Filter by role
            if ($request->has('role')) {
                $allActivations = $allActivations->filter(function ($activation) use ($request) {
                    return $activation->user && $activation->user->role === $request->role;
                });
            }
            
            // Sort by deactivate_at desc
            $allActivations = $allActivations->sortByDesc('deactivate_at');
            
            // Apply pagination manually
            $perPage = $request->get('per_page', 20);
            $page = $request->get('page', 1);
            $total = $allActivations->count();
            $offset = ($page - 1) * $perPage;
            
            $paginatedActivations = $allActivations->slice($offset, $perPage);
            
            $data = [
                'data' => $paginatedActivations->values(),
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total),
            ];

            return response()->json([
                'message' => 'Expired accounts retrieved successfully',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving expired accounts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get accounts expiring soon
     */
    public function getExpiringSoon(Request $request): JsonResponse
    {
        try {
            $currentUser = auth()->user();
            
            if (!$currentUser->isAdmin() && !$currentUser->isGlobalAdmin()) {
                return response()->json([
                    'message' => 'Unauthorized',
                    'error' => 'Only admins can access expiring accounts'
                ], 403);
            }

            $days = $request->get('days', 7);
            
            // Get all expiring activations first (since state is encrypted, we need to filter after decryption)
            $allActivations = Activation::with(['user'])->expiringSoon($days)->get();
            
            // Apply role-based filtering after decryption
            if ($currentUser->isAdmin() && !$currentUser->isGlobalAdmin()) {
                // Regional admin can only see activations from users in their state
                $allActivations = $allActivations->filter(function ($activation) use ($currentUser) {
                    return $activation->user && $activation->user->state === $currentUser->state;
                });
            }
            
            // Apply state filter for global admin if requested
            if ($currentUser->isGlobalAdmin() && $request->has('state') && $request->state !== 'all') {
                $allActivations = $allActivations->filter(function ($activation) use ($request) {
                    return $activation->user && $activation->user->state === $request->state;
                });
            }
            
            // Filter by role
            if ($request->has('role')) {
                $allActivations = $allActivations->filter(function ($activation) use ($request) {
                    return $activation->user && $activation->user->role === $request->role;
                });
            }
            
            // Sort by deactivate_at asc
            $allActivations = $allActivations->sortBy('deactivate_at');
            
            // Apply pagination manually
            $perPage = $request->get('per_page', 20);
            $page = $request->get('page', 1);
            $total = $allActivations->count();
            $offset = ($page - 1) * $perPage;
            
            $paginatedActivations = $allActivations->slice($offset, $perPage);
            
            $data = [
                'data' => $paginatedActivations->values(),
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total),
            ];

            return response()->json([
                'message' => 'Expiring accounts retrieved successfully',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving expiring accounts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending client approvals
     */
    public function getPendingClientApprovals(Request $request): JsonResponse
    {
        try {
            $currentUser = auth()->user();
            
            if (!$currentUser->isAdmin() && !$currentUser->isGlobalAdmin()) {
                return response()->json([
                    'message' => 'Unauthorized',
                    'error' => 'Only admins can view pending client approvals'
                ], 403);
            }

            // Get all users first (since state is encrypted, we need to filter after decryption)
            $allUsers = User::all();
            
            // Apply role-based filtering after decryption
            if ($currentUser->isAdmin() && !$currentUser->isGlobalAdmin()) {
                // Regional admin can only see users from their state
                $allUsers = $allUsers->filter(function ($user) use ($currentUser) {
                    return $user->state === $currentUser->state;
                });
            }
            
            // Apply state filter for global admin if requested
            if ($currentUser->isGlobalAdmin() && $request->has('state') && $request->state !== 'all') {
                $allUsers = $allUsers->filter(function ($user) use ($request) {
                    return $user->state === $request->state;
                });
            }
            
            // Filter for clients that are not approved
            $pendingClients = $allUsers->filter(function ($user) {
                return $user->role === 'client' && !$user->is_approved;
            })->values();

            // Apply pagination manually
            $perPage = $request->get('per_page', 20);
            $page = $request->get('page', 1);
            $total = $pendingClients->count();
            $offset = ($page - 1) * $perPage;
            
            $paginatedClients = $pendingClients->slice($offset, $perPage);
            
            $data = [
                'data' => $paginatedClients->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'role' => $user->role,
                        'state' => $user->state,
                        'is_active' => $user->is_active,
                        'is_approved' => $user->is_approved,
                        'created_at' => $user->created_at,
                    ];
                }),
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Pending client approvals retrieved successfully',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving pending client approvals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve client account
     */
    public function approveClient(Request $request, $userId): JsonResponse
    {
        try {
            $currentUser = auth()->user();
            
            if (!$currentUser->isAdmin() && !$currentUser->isGlobalAdmin()) {
                return response()->json([
                    'message' => 'Unauthorized',
                    'error' => 'Only admins can approve clients'
                ], 403);
            }

            $user = User::findOrFail($userId);
            
            // Check if admin can access this user (wilaya restriction for regional admins only)
            if ($currentUser->isAdmin() && !$currentUser->isGlobalAdmin() && $user->state !== $currentUser->state) {
                return response()->json([
                    'message' => 'Unauthorized',
                    'error' => 'You can only manage users from your wilaya'
                ], 403);
            }

            if ($user->role !== 'client') {
                return response()->json([
                    'message' => 'Invalid user',
                    'error' => 'Only client accounts can be approved'
                ], 400);
            }

            $user->approve();

            // Send approval email (non-blocking)
            try {
                $this->sendClientApprovalEmail($user);
            } catch (\Exception $e) {
                Log::error("Email sending failed but client approval succeeded: " . $e->getMessage());
                // Don't fail the approval if email fails
            }

            return response()->json([
                'message' => 'Client approved successfully',
                'data' => $user->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error approving client',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send client approval email
     */
    private function sendClientApprovalEmail(User $user): void
    {
        try {
            Log::info("Attempting to send client approval email to: {$user->email}");
            
            Mail::send('emails.client-approved', ['user' => $user], function ($message) use ($user) {
                $message->to($user->email, $user->name)
                        ->subject('Your Hani Account Has Been Approved!');
            });
            
            Log::info("Client approval email sent successfully to: {$user->email}");
        } catch (\Exception $e) {
            Log::error("Failed to send client approval email to {$user->email}: " . $e->getMessage());
            Log::error("Email error details: " . $e->getTraceAsString());
        }
    }
}