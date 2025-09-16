<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class DocumentController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    public function upload(Request $request, $userId): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);

            $request->validate([
                'documents' => 'required|array',
                'documents.*' => 'file|mimes:pdf|max:20480',
                'names' => 'array',
                'names.*' => 'string|nullable',
                'descriptions' => 'array',
                'descriptions.*' => 'string|nullable',
            ]);

            $uploaded = [];

            foreach ($request->file('documents', []) as $index => $file) {
                // Upload to Cloudinary
                $cloudinaryResult = $this->cloudinaryService->uploadDocument(
                    $file, 
                    "hani/documents/{$user->id}"
                );

                if (!$cloudinaryResult['success']) {
                    throw new \Exception('Cloudinary upload failed: ' . $cloudinaryResult['error']);
                }

                $doc = Document::create([
                    'user_id' => $user->id,
                    'name' => $request->input("names.$index") ?? $file->getClientOriginalName(),
                    'description' => $request->input("descriptions.$index"),
                    'file_path' => $cloudinaryResult['secure_url'], // Store Cloudinary URL
                ]);

                $uploaded[] = [
                    'id' => $doc->id,
                    'name' => $doc->name,
                    'description' => $doc->description,
                    'file_url' => $cloudinaryResult['secure_url'],
                    'public_id' => $cloudinaryResult['public_id'],
                    'created_at' => $doc->created_at,
                ];
            }

            return response()->json([
                'message' => 'Documents uploaded successfully',
                'data' => $uploaded,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Upload error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Server Error.' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function listByUser($userId): JsonResponse
    {
        try {
            $documents = Document::where('user_id', $userId)
                ->latest()
                ->get()
                ->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'name' => $doc->name,
                        'description' => $doc->description,
                        'file_path' => $doc->file_path, // This is now the Cloudinary URL
                        'file_url' => $doc->file_path, // Same as file_path since it's already a URL
                        'created_at' => $doc->created_at,
                    ];
                });

            return response()->json([
                'message' => 'Documents retrieved successfully',
                'data' => $documents,
            ]);

        } catch (\Exception $e) {
            Log::error('List documents error: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Server Error.' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function uploadByCredentials(Request $request): JsonResponse
{
    try {
        $validator = Validator::make($request->all(), [
            'email' => 'required_without:phone|email|nullable',
            'phone' => 'required_without:email|string|nullable',
            'password' => 'required|string',
            'documents' => 'required|array',
            'documents.*' => 'file|mimes:pdf|max:20480',
            'names' => 'array|nullable',
            'names.*' => 'string|nullable',
            'descriptions' => 'array|nullable',
            'descriptions.*' => 'string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (empty($request->email) && empty($request->phone)) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => ['Either email or phone must be provided'],
            ], 422);
        }

        // Find user with better query optimization
        $query = User::query();
        
        if (!empty($request->email)) {
            $email = strtolower(trim($request->email));
            $query->whereRaw('LOWER(TRIM(email)) = ?', [$email]);
        } elseif (!empty($request->phone)) {
            $phone = preg_replace('/\s+/', '', (string) $request->phone);
            $query->whereRaw("REPLACE(phone, ' ', '') = ?", [$phone]);
        }

        $user = $query->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found with the provided credentials',
            ], 404);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid password',
            ], 401);
        }

        // Handle documents upload to Cloudinary
        $uploaded = [];
        $documents = $request->file('documents');

        foreach ($documents as $index => $file) {
            if (!$file->isValid()) {
                throw new \Exception('Invalid file uploaded: ' . $file->getErrorMessage());
            }

            // Upload to Cloudinary
            $cloudinaryResult = $this->cloudinaryService->uploadDocument(
                $file, 
                "hani/documents/{$user->id}"
            );

            if (!$cloudinaryResult['success']) {
                throw new \Exception('Cloudinary upload failed: ' . $cloudinaryResult['error']);
            }

            $doc = Document::create([
                'user_id' => $user->id,
                'name' => $request->input("names.$index") ?? $file->getClientOriginalName(),
                'description' => $request->input("descriptions.$index", ''),
                'file_path' => $cloudinaryResult['secure_url'], // Store Cloudinary URL
            ]);

            $uploaded[] = [
                'id' => $doc->id,
                'name' => $doc->name,
                'description' => $doc->description,
                'file_url' => $cloudinaryResult['secure_url'],
                'public_id' => $cloudinaryResult['public_id'],
                'created_at' => $doc->created_at,
                'user_id' => $user->id,
            ];
        }

        return response()->json([
            'message' => 'Documents uploaded successfully',
            'data' => $uploaded,
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => 'Validation error',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        Log::error('Upload by credentials error: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'request' => $request->except(['password', 'documents'])
        ]);

        return response()->json([
            'message' => 'Upload failed: ' . $e->getMessage(),
            'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
        ], 500);
    }
}
}