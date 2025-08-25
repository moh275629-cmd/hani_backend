<?php

namespace App\Http\Controllers;

use App\Models\EditStore;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EditStoreController extends Controller
{
    /**
     * Submit a store edit request
     */
    public function submit(Request $request)
    {
        $user = Auth::user();
        
        // Debug: Log the incoming request
        \Log::info('Store edit request submitted by user ID: ' . $user->id);
        \Log::info('Request data:', $request->all());
        
        // Check if user has a store
        $store = Store::where('user_id', $user->id)->first();
        if (!$store) {
            \Log::error('Store not found for user ID: ' . $user->id);
            return response()->json([
                'success' => false,
                'message' => 'Store not found'
            ], 404);
        }

        \Log::info('Found store ID: ' . $store->id);

        // Validate request
        $validator = Validator::make($request->all(), [
            'store_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'business_type' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'logo' => 'nullable|string',
            'banner' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'state_code' => 'nullable|string|max:10',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'google_place_id' => 'nullable|string|max:255',
            'business_hours' => 'nullable|array',
            'payment_methods' => 'nullable|array',
            'services' => 'nullable|array',
            'user_name' => 'nullable|string|max:255',
            'user_phone' => 'nullable|string|max:255',
            'user_state' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if there's already a pending request
        $existingRequest = EditStore::where('store_id', $store->id)
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            \Log::warning('User already has a pending edit request. Request ID: ' . $existingRequest->id);
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending edit request'
            ], 400);
        }
        $authUser = Auth::user();

        // Prepare user data as JSON (optional, for storing additional info)
       
        
        // Create edit request
        $editRequest = EditStore::create([
            'store_id' => $store->id,
            'user_id' => $authUser->id,
            'store_name' => $request->input('store_name'),
            'description' => $request->input('description'),
            'business_type' => $request->input('business_type'),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'website' => $request->input('website'),
            'logo' => $request->input('logo'),
            'banner' => $request->input('banner'),
            'address' => $request->input('address'),
            'city' => $request->input('city'),
            'state' => $request->input('state'),
            'state_code' => $request->input('state_code'),
            'postal_code' => $request->input('postal_code'),
            'country' => $request->input('country'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'google_place_id' => $request->input('google_place_id'),
            'business_hours' => $request->input('business_hours'),
            'payment_methods' => $request->input('payment_methods'),
            'services' => $request->input('services'),
            'user_name'=>$request->input('user_name'),
            'user_phone'=>$request->input('user_phone'),
            'user_state'=>$request->input('user_state'),
        ]);

        \Log::info('Edit request created successfully. Request ID: ' . $editRequest->id);
        \Log::info('Edit request data:', $editRequest->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Store edit request submitted successfully. Waiting for admin approval.',
            'data' => $editRequest
        ]);
    }

    /**
     * Get user's pending edit requests
     */
    public function myRequests()
    {
        $user = Auth::user();
        
        $requests = EditStore::where('user_id', $user->id)
            ->with(['store'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    /**
     * Get edit request details
     */
    public function show($id)
    {
        $user = Auth::user();
        
        $editRequest = EditStore::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['store'])
            ->first();

        if (!$editRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Edit request not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $editRequest
        ]);
    }

    /**
     * Cancel a pending edit request
     */
    public function cancel($id)
    {
        $user = Auth::user();
        
        $editRequest = EditStore::where('id', $id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$editRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Edit request not found or cannot be cancelled'
            ], 404);
        }

        $editRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Edit request cancelled successfully'
        ]);
    }

    /**
     * Admin: Get all pending edit requests
     */
    public function adminIndex(Request $request)
    {
        $admin = Auth::user();
        
        // Check if admin has access to specific wilaya
        $wilayaCode = $request->input('wilaya_code');
        
        $query = EditStore::with(['store', 'user'])
            ->where('status', 'pending');

        if ($wilayaCode) {
            $query->where('state_code', $wilayaCode);
        }

        $requests = $query->orderBy('created_at', 'desc')->get();

        // Debug: Log the first request to see what's being returned
        if ($requests->count() > 0) {
            $firstRequest = $requests->first();
            \Log::info('First edit request data:', [
                'id' => $firstRequest->id,
                'store_id' => $firstRequest->store_id,
                'store_relationship' => $firstRequest->store ? $firstRequest->store->toArray() : 'null',
                'user_relationship' => $firstRequest->user ? $firstRequest->user->toArray() : 'null',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    /**
     * Admin: Get edit request details
     */
    public function adminShow($id)
    {
        $admin = Auth::user();
        
        $editRequest = EditStore::with(['store', 'user'])
            ->find($id);

        if (!$editRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Edit request not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $editRequest
        ]);
    }

    /**
     * Admin: Approve edit request
     */
    public function approve($id)
    {
        $admin = Auth::user();
        
        \Log::info('Admin approval request. Admin ID: ' . $admin->id . ', Request ID: ' . $id);
        
        $editRequest = EditStore::find($id);
        
        if (!$editRequest) {
            \Log::error('Edit request not found. Request ID: ' . $id);
            return response()->json([
                'success' => false,
                'message' => 'Edit request not found'
            ], 404);
        }

        \Log::info('Found edit request:', $editRequest->toArray());

        if ($editRequest->status !== 'pending') {
            \Log::warning('Edit request is not pending. Status: ' . $editRequest->status);
            return response()->json([
                'success' => false,
                'message' => 'Edit request is not pending'
            ], 400);
        }

        try {
            \Log::info('Starting approval process for request ID: ' . $id);
            $editRequest->approve($admin->id);
            \Log::info('Approval process completed successfully for request ID: ' . $id);
            
            return response()->json([
                'success' => true,
                'message' => 'Store edit request approved successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to approve edit request. Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve edit request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin: Reject edit request
     */
    public function reject(Request $request, $id)
    {
        $admin = Auth::user();
        
        $editRequest = EditStore::find($id);
        
        if (!$editRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Edit request not found'
            ], 404);
        }

        if ($editRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Edit request is not pending'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Rejection reason is required',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $editRequest->reject($admin->id, $request->input('rejection_reason'));
            
            return response()->json([
                'success' => true,
                'message' => 'Store edit request rejected successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject edit request: ' . $e->getMessage()
            ], 500);
        }
    }
}
