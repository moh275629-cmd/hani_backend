<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class DocumentController extends Controller
{
    public function upload(Request $request, $userId): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);

            $request->validate([
                'documents' => 'required|array',
                'documents.*' => 'file|mimes:jpg,jpeg,png,pdf|max:20480',
                'names' => 'array',
                'names.*' => 'string|nullable',
                'descriptions' => 'array',
                'descriptions.*' => 'string|nullable',
            ]);

            $uploaded = [];

            foreach ($request->file('documents', []) as $index => $file) {
                $storedPath = $file->store("documents/{$user->id}", 'public');

                $doc = Document::create([
                    'user_id' => $user->id,
                    'name' => $request->input("names.$index") ?? $file->getClientOriginalName(),
                    'description' => $request->input("descriptions.$index"),
                    'file_path' => $storedPath,
                ]);

                $uploaded[] = [
                    'id' => $doc->id,
                    'name' => $doc->name,
                    'description' => $doc->description,
                    'file_url' => Storage::disk('public')->url($storedPath),
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
                'message' => 'Server Error',
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
                        'file_path' => $doc->file_path,
                        'file_url' => Storage::disk('public')->url($doc->file_path),
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
                'message' => 'Server Error',
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
            'documents.*' => 'file|mimes:jpg,jpeg,png,pdf|max:20480',
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

        // Check that at least one of email or phone is provided
        if (empty($request->email) && empty($request->phone)) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => ['Either email or phone must be provided'],
            ], 422);
        }

        // Get all users and manually check encrypted fields
        $users = User::all();
        $user = null;

        foreach ($users as $potentialUser) {
            // Check if email matches (if provided)
            if (!empty($request->email) && 
                Hash::check($request->email, $potentialUser->email)) {
                $user = $potentialUser;
                break;
            }
            
            // Check if phone matches (if provided and email didn't match)
            if ($user === null && !empty($request->phone) && 
                Hash::check($request->phone, $potentialUser->phone)) {
                $user = $potentialUser;
                break;
            }
        }

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

        // Handle documents upload
        $uploaded = [];
        $documents = $request->file('documents');

        foreach ($documents as $index => $file) {
            if (!$file->isValid()) {
                throw new \Exception('Invalid file uploaded');
            }

            $storedPath = $file->store("documents/{$user->id}", 'public');

            $doc = Document::create([
                'user_id' => $user->id,
                'name' => $request->input("names.$index") ?? $file->getClientOriginalName(),
                'description' => $request->input("descriptions.$index", ''),
                'file_path' => $storedPath,
            ]);

            $uploaded[] = [
                'id' => $doc->id,
                'name' => $doc->name,
                'description' => $doc->description,
                'file_url' => Storage::disk('public')->url($storedPath),
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
            'message' => 'Server Error',
            'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
        ], 500);
    }
}
}