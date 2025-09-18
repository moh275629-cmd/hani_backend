<?php

namespace App\Http\Controllers;

use App\Models\BranchEditRequest;
use App\Models\Store;
use App\Models\StoreBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BranchEditRequestController extends Controller
{
    public function store(Request $request)
    {
        $user = Auth::user();
        $store = Store::where('user_id', $user->id)->first();
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:create,update,delete',
            'branch_id' => 'required_if:action,update,delete|integer|exists:store_branches,id',
            'branch_data' => 'required_if:action,create,update|array',
            'branch_data.wilaya_code' => 'required_if:action,create|string|max:10',
            'branch_data.city' => 'nullable|string|max:255',
            'branch_data.address' => 'nullable|string|max:500',
            'branch_data.phone' => 'nullable|string|max:50',
            'branch_data.latitude' => 'nullable|numeric|between:-90,90',
            'branch_data.longitude' => 'nullable|numeric|between:-180,180',
            'branch_data.gps_address' => 'nullable|string|max:1000',
            'branch_data.is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // For update and delete actions, verify the branch belongs to the store
        if (in_array($data['action'], ['update', 'delete'])) {
            $branch = StoreBranch::find($data['branch_id']);
            if (!$branch || $branch->store_id !== $store->id) {
                return response()->json(['message' => 'Branch not found or unauthorized'], 404);
            }
        }

        // Create the edit request
        $editRequest = BranchEditRequest::create([
            'store_id' => $store->id,
            'action' => $data['action'],
            'branch_id' => $data['branch_id'] ?? null,
            'branch_data' => $data['branch_data'] ?? null,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        return response()->json([
            'message' => 'Branch edit request submitted successfully',
            'data' => $editRequest
        ], 201);
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $store = Store::where('user_id', $user->id)->first();
        
        if (!$store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $requests = BranchEditRequest::where('store_id', $store->id)
            ->orderBy('requested_at', 'desc')
            ->get();

        return response()->json([
            'data' => $requests
        ]);
    }
}
