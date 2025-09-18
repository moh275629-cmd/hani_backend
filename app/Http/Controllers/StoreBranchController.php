<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\StoreBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class StoreBranchController extends Controller
{
    public function indexByStore($storeId)
    {
        $store = Store::find($storeId);
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }
        $branches = StoreBranch::where('store_id', $storeId)->get();
        return response()->json(['data' => $branches]);
    }

    public function myBranches()
    {
        $user = Auth::user();
        $store = Store::where('user_id', $user->id)->first();
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }
        $branches = StoreBranch::where('store_id', $store->id)->get();
        return response()->json(['data' => $branches]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $store = Store::where('user_id', $user->id)->first();
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'wilaya_code' => 'required|string|max:10',
            'city' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'gps_address' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['store_id'] = $store->id;
        $branch = StoreBranch::create($data);
        return response()->json(['message' => 'Branch created', 'data' => $branch], 201);
    }

    public function update(Request $request, $branchId)
    {
        $user = Auth::user();
        $branch = StoreBranch::find($branchId);
        if (!$branch) {
            return response()->json(['message' => 'Branch not found'], 404);
        }
        $store = Store::where('user_id', $user->id)->first();
        if ($branch->store_id !== $store->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'wilaya_code' => 'sometimes|string|max:10',
            'city' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'gps_address' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }
        $branch->update($validator->validated());
        return response()->json(['message' => 'Branch updated', 'data' => $branch]);
    }

    public function destroy($branchId)
    {
        $user = Auth::user();
        $branch = StoreBranch::find($branchId);
        if (!$branch) {
            return response()->json(['message' => 'Branch not found'], 404);
        }
        $store = Store::where('user_id', $user->id)->first();
        if ($branch->store_id !== $store->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $branch->delete();
        return response()->json(['message' => 'Branch deleted']);
    }
}
