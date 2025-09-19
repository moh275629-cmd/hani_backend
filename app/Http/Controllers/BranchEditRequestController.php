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

        // Determine the wilaya_code for the request
        $wilayaCode = null;
        if ($data['action'] === 'create' && isset($data['branch_data']['wilaya_code'])) {
            $wilayaCode = $data['branch_data']['wilaya_code'];
        } elseif (in_array($data['action'], ['update', 'delete']) && isset($data['branch_id'])) {
            $branch = StoreBranch::find($data['branch_id']);
            $wilayaCode = $branch ? $branch->wilaya_code : null;
        }

        // Create the edit request
        $editRequest = BranchEditRequest::create([
            'store_id' => $store->id,
            'action' => $data['action'],
            'branch_id' => $data['branch_id'] ?? null,
            'branch_data' => $data['branch_data'] ?? null,
            'wilaya_code' => $wilayaCode,
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

    // Method for wilaya admins to get requests for their wilaya
    public function getWilayaRequests(Request $request)
    {
        $user = Auth::user();
        $admin = $user->admin;
        
        if (!$admin || !$admin->wilaya_code) {
            return response()->json(['message' => 'Admin wilaya not found'], 404);
        }

        $requests = BranchEditRequest::where('wilaya_code', $admin->wilaya_code)
            ->where('status', 'pending')
            ->with(['store', 'branch'])
            ->orderBy('requested_at', 'desc')
            ->get();

        return response()->json([
            'data' => $requests
        ]);
    }

    // Method for wilaya admins to approve/reject requests
    public function processRequest(Request $request, $requestId)
    {
        $user = Auth::user();
        $admin = $user->admin;
        
        if (!$admin || !$admin->wilaya_code) {
            return response()->json(['message' => 'Admin wilaya not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $editRequest = BranchEditRequest::find($requestId);
        
        if (!$editRequest || $editRequest->wilaya_code !== $admin->wilaya_code) {
            return response()->json(['message' => 'Request not found or unauthorized'], 404);
        }

        if ($editRequest->status !== 'pending') {
            return response()->json(['message' => 'Request already processed'], 400);
        }

        $data = $validator->validated();

        // Update the request
        $editRequest->update([
            'status' => $data['status'],
            'processed_at' => now(),
            'processed_by' => $user->id,
            'admin_notes' => $data['admin_notes'] ?? null,
        ]);

        // If approved, execute the branch action
        if ($data['status'] === 'approved') {
            $this->executeBranchAction($editRequest);
        }

        return response()->json([
            'message' => "Branch request {$data['status']} successfully",
            'data' => $editRequest
        ]);
    }

    private function executeBranchAction(BranchEditRequest $editRequest)
    {
        switch ($editRequest->action) {
            case 'create':
                StoreBranch::create([
                    'store_id' => $editRequest->store_id,
                    'wilaya_code' => $editRequest->branch_data['wilaya_code'],
                    'city' => $editRequest->branch_data['city'] ?? null,
                    'address' => $editRequest->branch_data['address'] ?? null,
                    'phone' => $editRequest->branch_data['phone'] ?? null,
                    'latitude' => $editRequest->branch_data['latitude'] ?? null,
                    'longitude' => $editRequest->branch_data['longitude'] ?? null,
                    'gps_address' => $editRequest->branch_data['gps_address'] ?? null,
                    'is_active' => $editRequest->branch_data['is_active'] ?? true,
                ]);
                break;

            case 'update':
                if ($editRequest->branch_id) {
                    $branch = StoreBranch::find($editRequest->branch_id);
                    if ($branch) {
                        $branch->update($editRequest->branch_data);
                    }
                }
                break;

            case 'delete':
                if ($editRequest->branch_id) {
                    StoreBranch::destroy($editRequest->branch_id);
                }
                break;
        }
    }
}
