<?php

namespace App\Http\Controllers;

use App\Models\BusinessType;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class BusinessTypeController extends Controller
{
    /**
     * Get all active business types
     */
    public function index(): JsonResponse
    {
        try {
            $businessTypes = BusinessType::getActiveTypes();
            
            return response()->json([
                'success' => true,
                'data' => $businessTypes,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching business types',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get business types for dropdown (admin use)
     */
    public function getForDropdown(): JsonResponse
    {
        try {
            $businessTypes = BusinessType::getActiveTypes();
    
            $dropdown = $businessTypes->map(function ($type) {
                return [
                    'value' => $type->key,
                    'label' => $type->name,
                    'is_system_defined' => $type->is_system_defined,
                    'usage_count' => $type->usage_count,
                ];
            });
    
            // Separate "Other" from the rest
            $otherItem = $dropdown->firstWhere('label', 'Other');
            $regularItems = $dropdown->reject(fn($item) => $item['label'] === 'Other');
    
            // Sort regular items alphabetically
            $sortedRegularItems = $regularItems->sortBy(function ($item) {
                return strtolower($item['label']);
            })->values();
    
            // Combine sorted items with "Other" at the end
            $finalDropdown = $sortedRegularItems;
            if ($otherItem) {
                $finalDropdown = $finalDropdown->push($otherItem)->values();
            }
    
            return response()->json([
                'success' => true,
                'message' => 'Business types retrieved successfully',
                'data' => $finalDropdown,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching business types for dropdown',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    
    /**
     * Create a new business type (admin only)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:business_types,name',
                'key' => 'required|string|max:255|unique:business_types,key|regex:/^[a-z_]+$/',
                'description' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $businessType = BusinessType::create([
                'name' => $request->name,
                'key' => $request->key,
                'description' => $request->description,
                'is_active' => true,
                'is_system_defined' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Business type created successfully',
                'data' => $businessType,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating business type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a business type (admin only)
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $businessType = BusinessType::findOrFail($id);

            // Prevent modification of system-defined types
            if ($businessType->is_system_defined) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot modify system-defined business types'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:business_types,name,' . $id,
                'description' => 'nullable|string|max:1000',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $businessType->update($request->only(['name', 'description', 'is_active']));

            return response()->json([
                'success' => true,
                'message' => 'Business type updated successfully',
                'data' => $businessType,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating business type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a business type (admin only)
     */
    public function destroy($id): JsonResponse
    {
        try {
            $businessType = BusinessType::findOrFail($id);

            // Prevent deletion of system-defined types
            if ($businessType->is_system_defined) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete system-defined business types'
                ], 403);
            }

            // Check if any stores are using this business type
            $storeCount = Store::where('business_type', $businessType->key)->count();
            if ($storeCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete business type. $storeCount stores are currently using this type."
                ], 409);
            }

            $businessType->delete();

            return response()->json([
                'success' => true,
                'message' => 'Business type deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting business type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a custom business type from a store
     */
    public function approveCustomType(Request $request, $storeId): JsonResponse
    {
        try {
            $store = Store::findOrFail($storeId);

            if (!$store->has_custom_business_type || !$store->custom_business_type) {
                return response()->json([
                    'success' => false,
                    'message' => 'Store does not have a custom business type to approve'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'action' => 'required|in:approve,assign',
                'business_type_key' => 'required_if:action,assign|string|exists:business_types,key',
                'custom_type_text' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            if ($request->action === 'approve') {
                // Use edited text if provided, otherwise use the original custom business type
                $customTypeText = $request->custom_type_text ?? $store->custom_business_type;
                
                $key = strtolower(str_replace([' ', '-', '.'], '_', $customTypeText));
                $key = preg_replace('/[^a-z_]/', '', $key);
                
                // Ensure key is unique
                $originalKey = $key;
                $counter = 1;
                while (BusinessType::where('key', $key)->exists()) {
                    $key = $originalKey . '_' . $counter;
                    $counter++;
                }

                $businessType = BusinessType::create([
                    'name' => $customTypeText,
                    'key' => $key,
                    'description' => "Custom business type created from store: {$store->store_name}",
                    'is_active' => true,
                    'is_system_defined' => false,
                ]);

                // Update store to use the new business type
                $store->update([
                    'business_type' => $key,
                    'has_custom_business_type' => false,
                    'custom_business_type' => null,
                ]);

                $businessType->incrementUsage();

            } else {
                // Assign to existing business type
                $store->update([
                    'business_type' => $request->business_type_key,
                    'has_custom_business_type' => false,
                    'custom_business_type' => null,
                ]);

                $businessType = BusinessType::where('key', $request->business_type_key)->first();
                $businessType->incrementUsage();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Custom business type processed successfully',
                'data' => [
                    'store' => $store->fresh(),
                    'business_type' => $businessType ?? null,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error processing custom business type',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
