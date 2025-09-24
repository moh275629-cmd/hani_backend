<?php

namespace App\Http\Controllers;

use App\Models\Wilaya;
use App\Models\User;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WilayaController extends Controller
{
    /**
     * Get all wilayas with pagination and filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Wilaya::with(['admin.user', 'creator', 'updater']);

            // Apply filters
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name_en', 'like', "%{$search}%")
                      ->orWhere('name_fr', 'like', "%{$search}%")
                      ->orWhere('name_ar', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('has_admin')) {
                if ($request->boolean('has_admin')) {
                    $query->withAdmin();
                } else {
                    $query->withoutAdmin();
                }
            }

            $wilayas = $query->orderByRaw('CAST(code AS UNSIGNED)')->get();

            return response()->json([
                'success' => true,
                'data' => $wilayas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch wilayas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get wilayas with admins
     */
    public function withAdmins(Request $request): JsonResponse
    {
        try {
            // Get wilayas with admins
            $withAdminsQuery = Wilaya::with(['admin.user', 'creator', 'updater'])
    ->whereHas('admin', function ($query) {
        $query->whereColumn('admins.wilaya_code', 'wilayas.code');
    });
                
            // Get wilayas without admins
            $withoutAdminsQuery = Wilaya::with(['creator', 'updater'])
                ->whereDoesntHave('admin');
                
            // Apply search filter to both queries if provided
            if ($request->has('search')) {
                $search = $request->search;
                $searchFunction = function ($q) use ($search) {
                    $q->where('name_en', 'like', "%{$search}%")
                      ->orWhere('name_fr', 'like', "%{$search}%")
                      ->orWhere('name_ar', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                };
                
                $withAdminsQuery->where($searchFunction);
                $withoutAdminsQuery->where($searchFunction);
            }
    
            // Apply active filter to both queries if provided
            if ($request->has('is_active')) {
                $isActive = $request->boolean('is_active');
                $withAdminsQuery->where('is_active', $isActive);
                $withoutAdminsQuery->where('is_active', $isActive);
            }
            $querywilayas = Wilaya::with(['admin.user', 'creator', 'updater']);
            $wilayas = $querywilayas->orderByRaw('CAST(code AS UNSIGNED)')->get();
            $wilayasWithAdmins = $withAdminsQuery->orderByRaw('CAST(code AS UNSIGNED)')->get();
            $wilayasWithoutAdmins = $withoutAdminsQuery->orderByRaw('CAST(code AS UNSIGNED)')->get();
    
            return response()->json([
                'success' => true,
                'data' => [
                    'all_wilayas' => $wilayas,
                    'with_admins' => $wilayasWithAdmins,
                    'without_admins' => $wilayasWithoutAdmins,
                    'stats' => [
                        'total_with_admins' => $wilayasWithAdmins->count(),
                        'total_without_admins' => $wilayasWithoutAdmins->count(),
                        'total_wilayas' => $wilayasWithAdmins->count() + $wilayasWithoutAdmins->count()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch wilayas: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Get wilaya by ID
     */
    public function show($id): JsonResponse
    {
        try {
            $wilaya = Wilaya::with(['admin.user', 'creator', 'updater'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $wilaya
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Wilaya not found'
            ], 404);
        }
    }

    /**
     * Create new wilaya
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|max:10|unique:wilayas,code',
                'name_en' => 'required|string|max:255',
                'name_fr' => 'required|string|max:255',
                'name_ar' => 'required|string|max:255',
                'is_active' => 'boolean',
                
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $wilaya = Wilaya::createWilaya([
                'code' => $request->code,
                'name_en' => $request->name_en,
                'name_fr' => $request->name_fr,
                'name_ar' => $request->name_ar,
                'is_active' => $request->get('is_active', true),
                
                'created_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Wilaya created successfully',
                'data' => $wilaya->load(['creator'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create wilaya: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update wilaya
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $wilaya = Wilaya::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'code' => 'sometimes|string|max:10|unique:wilayas,code,' . $id,
                'name_en' => 'sometimes|string|max:255',
                'name_fr' => 'sometimes|string|max:255',
                'name_ar' => 'sometimes|string|max:255',
                'is_active' => 'boolean',
              
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $wilaya->update([
                'code' => $request->get('code', $wilaya->code),
                'name_en' => $request->get('name_en', $wilaya->name_en),
                'name_fr' => $request->get('name_fr', $wilaya->name_fr),
                'name_ar' => $request->get('name_ar', $wilaya->name_ar),
                'is_active' => $request->get('is_active', $wilaya->is_active),
                    'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Wilaya updated successfully',
                'data' => $wilaya->load(['updater'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update wilaya: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete wilaya
     */
    public function destroy($id): JsonResponse
    {
        try {
            $wilaya = Wilaya::findOrFail($id);

            // Check if wilaya has stores or users
            if ($wilaya->stores()->count() > 0 || $wilaya->users()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete wilaya with existing stores or users'
                ], 400);
            }

            $wilaya->delete();

            return response()->json([
                'success' => true,
                'message' => 'Wilaya deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete wilaya: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign admin to wilaya
     */
    public function assignAdmin(Request $request, $id): JsonResponse
    {
        try {
            $wilaya = Wilaya::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'admin_user_id' => 'required|exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $admin = User::findOrFail($request->admin_user_id);

            // Check if user is already admin of another wilaya
            $existingWilaya = Wilaya::where('admin_user_id', $admin->id)->first();
            if ($existingWilaya && $existingWilaya->id !== $wilaya->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is already admin of another wilaya'
                ], 400);
            }

            // Update user role to admin if not already
            if ($admin->role !== 'admin') {
                $admin->update(['role' => 'admin']);
            }

            $wilaya->assignAdmin($admin->id);

            return response()->json([
                'success' => true,
                'message' => 'Admin assigned successfully',
                'data' => $wilaya->load(['admin'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign admin: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove admin from wilaya
     */
    public function removeAdmin($id): JsonResponse
    {
        try {
            $wilaya = Wilaya::findOrFail($id);

            if (!$wilaya->hasAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Wilaya has no assigned admin'
                ], 400);
            }

            $wilaya->removeAdmin();

            return response()->json([
                'success' => true,
                'message' => 'Admin removed successfully',
                'data' => $wilaya
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove admin: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get wilayas without admin
     */
    public function withoutAdmin(): JsonResponse
    {
        try {
            $wilayas = Wilaya::getWilayasWithoutAdmin();

            return response()->json([
                'success' => true,
                'data' => $wilayas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch wilayas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get wilaya statistics
     */
    public function statistics($id): JsonResponse
    {
        try {
            $wilaya = Wilaya::findOrFail($id);

            $stats = [
                'total_stores' => $wilaya->stores()->count(),
                'active_stores' => $wilaya->stores()->where('is_active', true)->count(),
                'total_users' => $wilaya->users()->count(),
                'has_admin' => $wilaya->hasAdmin(),
                'admin_name' => $wilaya->admin ? $wilaya->admin->user->name : null,
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}
