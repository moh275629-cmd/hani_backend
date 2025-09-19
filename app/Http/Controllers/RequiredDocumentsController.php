<?php

namespace App\Http\Controllers;

use App\Models\RequiredDocuments;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class RequiredDocumentsController extends Controller
{
    /**
     * Get all required documents
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = RequiredDocuments::query();

            if ($request->has('user_category')) {
                $query->where('user_category', $request->user_category);
            }

            if ($request->has('document_type')) {
                $query->where('document_type', $request->document_type);
            }

            if ($request->has('is_required')) {
                $query->where('is_required', $request->boolean('is_required'));
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $documents = $query->ordered()->get();

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
     * Get documents for a specific user category
     */
    public function getForCategory(Request $request, $category): JsonResponse
    {
        try {
            $documents = RequiredDocuments::getForUserCategory($category);

            return response()->json([
                'message' => 'Documents retrieved successfully',
                'data' => $documents
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get required documents for a specific user category
     */
    public function getRequiredForCategory(Request $request, $category): JsonResponse
    {
        try {
            $documents = RequiredDocuments::getRequiredForUserCategory($category);

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
     * Create a new required document
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name_ar' => 'required|string|max:255',
            'name_fr' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'description_ar' => 'nullable|string',
            'description_fr' => 'nullable|string',
            'description_en' => 'nullable|string',
            'document_type' => 'required|in:identity,business,financial,legal,other',
            'user_category' => 'required|in:client,store,admin',
            'file_types' => 'required|array',
            'file_types.*' => 'string|max:50',
            'max_file_size' => 'required|integer|min:1',
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
            $document = RequiredDocuments::create($request->all());

            return response()->json([
                'message' => 'Required document created successfully',
                'data' => $document
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating required document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a required document
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $document = RequiredDocuments::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name_ar' => 'sometimes|required|string|max:255',
                'name_fr' => 'sometimes|required|string|max:255',
                'name_en' => 'sometimes|required|string|max:255',
                'description_ar' => 'nullable|string',
                'description_fr' => 'nullable|string',
                'description_en' => 'nullable|string',
                'document_type' => 'sometimes|required|in:identity,business,financial,legal,other',
                'user_category' => 'sometimes|required|in:client,store,admin',
                'file_types' => 'sometimes|required|array',
                'file_types.*' => 'string|max:50',
                'max_file_size' => 'sometimes|required|integer|min:1',
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

            $document->update($request->all());

            return response()->json([
                'message' => 'Required document updated successfully',
                'data' => $document
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating required document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a required document
     */
    public function destroy($id): JsonResponse
    {
        try {
            $document = RequiredDocuments::findOrFail($id);
            $document->delete();

            return response()->json([
                'message' => 'Required document deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting required document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get document types
     */
    public function getDocumentTypes(): JsonResponse
    {
        return response()->json([
            'message' => 'Document types retrieved successfully',
            'data' => RequiredDocuments::getDocumentTypes()
        ]);
    }

    /**
     * Get user categories
     */
    public function getUserCategories(): JsonResponse
    {
        return response()->json([
            'message' => 'User categories retrieved successfully',
            'data' => RequiredDocuments::getUserCategories()
        ]);
    }

    /**
     * Get file types
     */
    public function getFileTypes(): JsonResponse
    {
        return response()->json([
            'message' => 'File types retrieved successfully',
            'data' => RequiredDocuments::getFileTypes()
        ]);
    }
}
