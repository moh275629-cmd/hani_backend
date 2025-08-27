<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\TermsAndConditions;
use App\Models\RequiredDocuments;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class GlobalAdminController extends Controller
{
    /**
     * Get all admins with pagination and filters
     */
    public function admins(Request $request): JsonResponse
    {
        try {
            $query = User::query();

            // Apply filters
            if ($request->has('search')) {
                $search = $request->search;
                $query->searchByEncryptedFields($search);
            }

            if ($request->has('role')) {
                // roles are encrypted; filter after decryption by ids
                $role = $request->role;
                $all = User::all();
                $matchingIds = $all->filter(function($u) use ($role) {
                    try { return (string) $u->role === (string) $role; } catch (\Exception $e) { return false; }
                })->pluck('id');
                if ($matchingIds->isNotEmpty()) {
                    $query->whereIn('id', $matchingIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            } else {
                // Default to admins and global_admins via decrypted values
                $all = User::all();
                $matchingIds = $all->filter(function($u) {
                    try { return in_array($u->role, ['admin','global_admin'], true); } catch (\Exception $e) { return false; }
                })->pluck('id');
                if ($matchingIds->isNotEmpty()) {
                    $query->whereIn('id', $matchingIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            if ($request->has('status')) {
                $query->where('is_active', $request->status === 'active');
            }

            $admins = $query->paginate($request->get('per_page', 15));

            // Transform the data to handle encrypted fields
            $transformedData = $admins->getCollection()->map(function ($admin) {
                return [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'phone' => $admin->phone,
                    'role' => $admin->role,
                    'state' => $admin->state,
                    'is_active' => $admin->is_active,
                    'email_verified_at' => $admin->email_verified_at,
                    'phone_verified_at' => $admin->phone_verified_at,
                    'created_at' => $admin->created_at,
                    'updated_at' => $admin->updated_at,
                ];
            });

            $paginationData = $admins->toArray();
            $paginationData['data'] = $transformedData;

            return response()->json([
                'message' => 'Admins retrieved successfully',
                'data' => $paginationData
            ]);

        } catch (\Exception $e) {
            \Log::error('Error retrieving admins: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Error retrieving admins',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new admin
     */
    public function createAdmin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
            'role' => 'sometimes|in:admin,global_admin',
            'state' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Check if email already exists (handles encrypted emails)
            $existingUser = User::findByEmail($request->email);
            if ($existingUser) {
                return response()->json([
                    'message' => 'Email already exists',
                    'errors' => ['email' => ['The email has already been taken.']]
                ], 422);
            }

            // Check if phone already exists (handles encrypted phones)
            if ($request->phone) {
                $existingUserByPhone = User::findByPhone($request->phone);
                if ($existingUserByPhone) {
                    return response()->json([
                        'message' => 'Phone number already exists',
                        'errors' => ['phone' => ['The phone number has already been taken.']]
                    ], 422);
                }
            }

            $admin = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => $request->get('role', 'admin'),
                'state' => $request->state,
                'is_active' => true,
                'email_verified_at' => now(), // Auto-verify admin emails
                'phone_verified_at' => now(), // Auto-verify admin phones
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Admin created successfully',
                'data' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'phone' => $admin->phone,
                    'role' => $admin->role,
                    'state' => $admin->state,
                    'is_active' => $admin->is_active,
                    'email_verified_at' => $admin->email_verified_at,
                    'phone_verified_at' => $admin->phone_verified_at,
                    'created_at' => $admin->created_at,
                    'updated_at' => $admin->updated_at,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creating admin: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Error creating admin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update admin details
     */
    public function updateAdmin(Request $request, $admin): JsonResponse
    {
        try {
            // Resolve ID from route param to avoid any route-model binding issues
            $adminId = is_numeric($admin) ? (int) $admin : (int) $request->route('admin');
            if ($adminId <= 0) {
                return response()->json([
                    'message' => 'Invalid admin id',
                    'updated' => false,
                    'id' => null,
                    'db_connection' => config('database.default'),
                    'db_database' => (string) DB::connection()->getDatabaseName(),
                ], 400);
            }

            // Find the admin directly
            $adminModel = User::find($adminId);
            if (!$adminModel) {
                return response()->json([
                    'message' => 'Admin not found',
                    'updated' => false,
                    'id' => $adminId,
                    'db_connection' => config('database.default'),
                    'db_database' => (string) DB::connection()->getDatabaseName(),
                ], 404);
            }

            // Ensure we're only updating admin users
            if (!in_array($adminModel->role, ['admin', 'global_admin'])) {
                return response()->json([
                    'message' => 'User is not an admin',
                    'error' => 'Can only update admin users'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:users,email,' . $adminModel->id,
                'phone' => 'nullable|string|max:20',
                'password' => 'nullable|string|min:8',
                'role' => 'sometimes|required|in:admin,global_admin',
                'state' => 'nullable|string|max:255',
                'is_active' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $updateData = $request->only(['name', 'email', 'phone', 'role', 'state', 'is_active']);
            
            if ($request->has('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $adminModel->update($updateData);

            DB::commit();

            return response()->json([
                'message' => 'Admin updated successfully',
                'data' => $adminModel,
                'updated' => true,
                'id' => $adminId,
                'db_connection' => config('database.default'),
                'db_database' => (string) DB::connection()->getDatabaseName(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error updating admin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete admin
     */
    public function deleteAdmin(int $adminId): JsonResponse
    {
        // Ensure we're only deleting admin users
        if (!in_array($admin->role, ['admin', 'global_admin'])) {
            return response()->json([
                'message' => 'User is not an admin',
                'error' => 'Can only delete admin users'
            ], 400);
        }

        // Prevent self-deletion
        if ($admin->id === auth()->id()) {
            return response()->json([
                'message' => 'Cannot delete your own account',
                'error' => 'Self-deletion not allowed'
            ], 400);
        }

        try {

            $admin = User::find($adminId);
            if (!$admin) {
                return response()->json([
                    'message' => 'Admin not found',
                    'deleted' => 0,
                    'id' => null,
                    'db_connection' => config('database.default'),
                    'db_database' => (string) DB::connection()->getDatabaseName(),
                ], 404);
            }

            $deletedRows =  User::where('id', $adminId)->delete();

            return response()->json([
                'message' => $deletedRows === 1 ? 'Admin deleted successfully' : 'Admin not found or already deleted',
                'deleted' => (int) $deletedRows,
                'id' => $adminId,
                'db_connection' => config('database.default'),
                'db_database' => (string) DB::connection()->getDatabaseName(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting admin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all terms and conditions
     */
    public function termsAndConditions(Request $request): JsonResponse
    {
        try {
            $query = TermsAndConditions::query();

            if ($request->has('active')) {
                $query->where('is_active', $request->boolean('active'));
            }

            $terms = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

            return response()->json([
                'message' => 'Terms and conditions retrieved successfully',
                'data' => $terms
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving terms and conditions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create terms and conditions
     */
    public function createTermsAndConditions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content_en' => 'nullable|string',
            'content_fr' => 'nullable|string',
            'content_ar' => 'nullable|string',
            
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // If new terms are active, deactivate all others
            if ($request->boolean('is_active', true)) {
                TermsAndConditions::where('is_active', true)->update(['is_active' => false]);
            }

            $terms = TermsAndConditions::create([
                'content_en' => $request->content_en,
                'content_fr' => $request->content_fr,
                'content_ar' => $request->content_ar,
                
                'is_active' => $request->boolean('is_active', true),
                'is_published' => true,
                'publisher_id' => auth()->id(),
                'published_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Terms and conditions created successfully',
                'data' => $terms
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error creating terms and conditions',
                'error' => $e->getMessage()

            ], 500);
        \Log::error('Error creating terms and conditions: ' . $e->getMessage());
        }
    }

    /**
     * Update terms and conditions
     */
    public function updateTermsAndConditions(Request $request, $terms): JsonResponse
    {
        try {
            // Resolve ID from route param to avoid any route-model binding issues
            $termsId = is_numeric($terms) ? (int) $terms : (int) $request->route('terms');
            if ($termsId <= 0) {
                return response()->json([
                    'message' => 'Invalid terms id',
                    'updated' => false,
                    'id' => null,
                    'db_connection' => config('database.default'),
                    'db_database' => (string) DB::connection()->getDatabaseName(),
                ], 400);
            }

            // Find the terms directly
            $termsModel = TermsAndConditions::find($termsId);
            if (!$termsModel) {
                return response()->json([
                    'message' => 'Terms and conditions not found',
                    'updated' => false,
                    'id' => $termsId,
                    'db_connection' => config('database.default'),
                    'db_database' => (string) DB::connection()->getDatabaseName(),
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'content_en' => 'sometimes|required|string',
                'content_fr' => 'sometimes|required|string',
                'content_ar' => 'sometimes|required|string',
               
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // If terms are being activated, deactivate all others
            if ($request->boolean('is_active') && !$termsModel->is_active) {
                TermsAndConditions::where('is_active', true)->update(['is_active' => false]);
            }

            $termsModel->update($request->only(['content_en', 'content_fr', 'content_ar',  'is_active']));

            DB::commit();

            return response()->json([
                'message' => 'Terms and conditions updated successfully',
                'data' => $termsModel,
                'updated' => true,
                'id' => $termsId,
                'db_connection' => config('database.default'),
                'db_database' => (string) DB::connection()->getDatabaseName(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error updating terms and conditions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete terms and conditions
     */
    public function deleteTermsAndConditions(int $termsId): JsonResponse
    {
        try {
            $terms = TermsAndConditions::find($termsId);
            if (!$terms) {
                return response()->json([
                    'message' => 'Terms and conditions not found',
                    'deleted' => 0,
                    'id' => null,
                    'db_connection' => config('database.default'),
                    'db_database' => (string) DB::connection()->getDatabaseName(),
                ], 404);
            }

            $deletedRows = TermsAndConditions::where('id', $termsId)->delete();

            return response()->json([
                'message' => $deletedRows === 1 ? 'Terms and conditions deleted successfully' : 'Terms and conditions not found or already deleted',
                'deleted' => (int) $deletedRows,
                'id' => $termsId,
                'db_connection' => config('database.default'),
                'db_database' => (string) DB::connection()->getDatabaseName(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting terms and conditions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all required documents
     */
    public function requiredDocuments(Request $request): JsonResponse
    {
        try {
            $query = RequiredDocuments::query();

            if ($request->has('role')) {
                // Map 'role' filter from UI to users.role or user_category
                $query->where('user_category', $request->role);
            }

            if ($request->has('required')) {
                $query->where('is_required', $request->boolean('required'));
            }

            $documents = $query->orderBy('display_order')->paginate($request->get('per_page', 15));

            return response()->json([
                'message' => 'Required documents retrieved successfully',
                'data' => $documents
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving required documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create required document
     */
    public function createRequiredDocument(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name_en' => 'nullable|string|max:255',
            'name_fr' => 'nullable|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description_en' => 'nullable|string',
            'description_fr' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'document_type' => 'nullable|in:identity,business,financial,legal,other',
            'user_category' => 'nullable|in:client,store,admin',
            'file_types' => 'nullable|array',
            'file_types.*' => 'string|max:50',
            'max_file_size' => 'nullable|integer|min:1',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'display_order' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Set defaults for required fields
            $data = $request->all();
            
            // Ensure at least one name is provided
            if (empty($data['name_en']) && empty($data['name_fr']) && empty($data['name_ar'])) {
                return response()->json([
                    'message' => 'At least one name (name_en, name_fr, or name_ar) is required',
                    'errors' => ['name_en' => ['At least one name is required']]
                ], 422);
            }

            // Set defaults
            $data['document_type'] = $data['document_type'] ?? 'other';
            $data['user_category'] = $data['user_category'] ?? 'client';
            $data['file_types'] = $data['file_types'] ?? ['pdf', 'jpg', 'jpeg', 'png'];
            $data['max_file_size'] = $data['max_file_size'] ?? 2048; // 2MB default
            $data['is_required'] = $data['is_required'] ?? true;
            $data['is_active'] = $data['is_active'] ?? true;
            $data['display_order'] = $data['display_order'] ?? 0;

            $document = RequiredDocuments::create($data);

            return response()->json([
                'message' => 'Required document created successfully',
                'data' => $document
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Error creating required document: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Error creating required document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update required document
     */
    public function updateRequiredDocument(Request $request, $document): JsonResponse
    {
        try {
            // Resolve ID from route param to avoid any route-model binding issues
            $documentId = is_numeric($document) ? (int) $document : (int) $request->route('document');
            if ($documentId <= 0) {
                return response()->json([
                    'message' => 'Invalid document id',
                    'updated' => false,
                    'id' => null,
                    'db_connection' => config('database.default'),
                    'db_database' => (string) DB::connection()->getDatabaseName(),
                ], 400);
            }

            // Find the document directly
            $documentModel = RequiredDocuments::find($documentId);
            if (!$documentModel) {
                return response()->json([
                    'message' => 'Required document not found',
                    'updated' => false,
                    'id' => $documentId,
                    'db_connection' => config('database.default'),
                    'db_database' => (string) DB::connection()->getDatabaseName(),
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name_en' => 'sometimes|required|string|max:255',
                'name_fr' => 'sometimes|required|string|max:255',
                'name_ar' => 'sometimes|required|string|max:255',
                'description_en' => 'nullable|string',
                'description_fr' => 'nullable|string',
                'description_ar' => 'nullable|string',
                'document_type' => 'sometimes|required|in:identity,business,financial,legal,other',
                'user_category' => 'sometimes|required|in:client,store,admin',
                'file_types' => 'sometimes|required|array',
                'max_file_size' => 'sometimes|required|integer|min:1',
                'is_required' => 'boolean',
                'is_active' => 'boolean',
                'display_order' => 'sometimes|required|integer|min:0',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $documentModel->update($request->all());

            return response()->json([
                'message' => 'Required document updated successfully',
                'data' => $documentModel,
                'updated' => true,
                'id' => $documentId,
                'db_connection' => config('database.default'),
                'db_database' => (string) DB::connection()->getDatabaseName(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating required document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete required document
     */
    public function deleteRequiredDocument(int $documentId): JsonResponse
    {
        try {

            $document = RequiredDocuments::find($documentId);
            if (!$document) {
                return response()->json([
                    'message' => 'Required document not found',
                    'deleted' => 0,
                    'id' => null,
                    'db_connection' => config('database.default'),
                    'db_database' => (string) DB::connection()->getDatabaseName(),
                ], 404);
            }

            $deletedRows = RequiredDocuments::where('id', $documentId)->delete();

            return response()->json([
                'message' => $deletedRows === 1 ? 'Required document deleted successfully' : 'Required document not found or already deleted',
                'deleted' => (int) $deletedRows,
                'id' => $documentId,
                'db_connection' => config('database.default'),
                'db_database' => (string) DB::connection()->getDatabaseName(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting required document',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
