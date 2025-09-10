<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Wilaya;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class CityController extends Controller
{
    /**
     * Get all cities with pagination and filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = City::with(['wilaya', 'creator', 'updater']);

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

            if ($request->has('wilaya_code')) {
                $query->where('wilaya_code', $request->wilaya_code);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $cities = $query->orderBy('name_en')->paginate(50);

            return response()->json([
                'success' => true,
                'data' => $cities
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch cities: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cities by wilaya
     */
    public function getByWilaya($wilayaCode): JsonResponse
    {
        try {
            $cities = City::getByWilaya($wilayaCode);

            return response()->json([
                'success' => true,
                'data' => $cities
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch cities: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get city by ID
     */
    public function show($id): JsonResponse
    {
        try {
            $city = City::with(['wilaya', 'creator', 'updater'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $city
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'City not found'
            ], 404);
        }
    }

    /**
     * Create new city
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|max:10|unique:cities,code',
                'name_en' => 'required|string|max:255',
                'name_fr' => 'required|string|max:255',
                'name_ar' => 'required|string|max:255',
                'wilaya_code' => 'required|exists:wilayas,code',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $city = City::createCity([
                'code' => $request->code,
                'name_en' => $request->name_en,
                'name_fr' => $request->name_fr,
                'name_ar' => $request->name_ar,
                'wilaya_code' => $request->wilaya_code,
                'is_active' => $request->get('is_active', true),
                'created_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'City created successfully',
                'data' => $city->load(['wilaya', 'creator'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create city: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update city
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $city = City::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'code' => 'sometimes|string|max:10|unique:cities,code,' . $id,
                'name_en' => 'sometimes|string|max:255',
                'name_fr' => 'sometimes|string|max:255',
                'name_ar' => 'sometimes|string|max:255',
                'wilaya_code' => 'sometimes|exists:wilayas,code',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $city->update([
                'code' => $request->get('code', $city->code),
                'name_en' => $request->get('name_en', $city->name_en),
                'name_fr' => $request->get('name_fr', $city->name_fr),
                'name_ar' => $request->get('name_ar', $city->name_ar),
                'wilaya_code' => $request->get('wilaya_code', $city->wilaya_code),
                'is_active' => $request->get('is_active', $city->is_active),
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'City updated successfully',
                'data' => $city->load(['wilaya', 'updater'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update city: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete city
     */
    public function destroy($id): JsonResponse
    {
        try {
            $city = City::findOrFail($id);

            // Check if city has stores or users
            if ($city->stores()->count() > 0 || $city->users()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete city with existing stores or users'
                ], 400);
            }

            $city->delete();

            return response()->json([
                'success' => true,
                'message' => 'City deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete city: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search cities
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $query = $request->get('q', '');
            $wilayaCode = $request->get('wilaya_code');

            $citiesQuery = City::where(function ($q) use ($query) {
                $q->where('name_en', 'like', "%{$query}%")
                  ->orWhere('name_fr', 'like', "%{$query}%")
                  ->orWhere('name_ar', 'like', "%{$query}%")
                  ->orWhere('code', 'like', "%{$query}%");
            });

            if ($wilayaCode) {
                $citiesQuery->where('wilaya_code', $wilayaCode);
            }

            $cities = $citiesQuery->where('is_active', true)
                                 ->with('wilaya')
                                 ->orderBy('name_en')
                                 ->limit(20)
                                 ->get();

            return response()->json([
                'success' => true,
                'data' => $cities
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search cities: ' . $e->getMessage()
            ], 500);
        }
    }
}
