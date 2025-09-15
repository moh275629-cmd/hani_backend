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

            // Because email/phone are encrypted at rest, fetch candidates then match on decrypted getters
            $candidateUsers = User::query()
                ->select(['id', 'email', 'phone', 'password'])
                ->get();

            $user = null;
            if (!empty($request->email)) {
                $needle = strtolower(trim($request->email));
                $user = $candidateUsers->first(function ($u) use ($needle) {
                    return strtolower(trim((string) $u->email)) === $needle;
                });
            } elseif (!empty($request->phone)) {
                $needle = preg_replace('/\s+/', '', (string) $request->phone);
                $user = $candidateUsers->first(function ($u) use ($needle) {
                    $phone = preg_replace('/\s+/', '', (string) $u->phone);
                    return $phone === $needle;
                });
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

            // Collect files
            $normalizedFiles = [];
            if ($request->hasFile('documents')) {
                $files = $request->file('documents');
                if (is_array($files)) {
                    $normalizedFiles = $files;
                } elseif ($files !== null) {
                    $normalizedFiles = [$files];
                }
            } else {
                foreach ($request->allFiles() as $key => $value) {
                    if (str_starts_with($key, 'documents')) {
                        if (is_array($value)) {
                            foreach ($value as $f) { $normalizedFiles[] = $f; }
                        } else {
                            $normalizedFiles[] = $value;
                        }
                    }
                }
            }

            if (empty($normalizedFiles)) {
                return response()->json([
                    'message' => 'No documents found in request',
                ], 400);
            }

            foreach ($normalizedFiles as $f) {
                if (!$f->isValid()) {
                    return response()->json([
                        'message' => 'One or more files are invalid',
                    ], 400);
                }
                $mime = $f->getClientMimeType();
                $sizeKb = (int) ceil($f->getSize() / 1024);
                if (!in_array($mime, ['image/jpeg','image/jpg', 'image/png', 'application/pdf'])) {
                    return response()->json([
                        'message' => 'Unsupported file type',
                    ], 415);
                }
                if ($sizeKb > 20480) {
                    return response()->json([
                        'message' => 'File too large (max 20MB)',
                    ], 413);
                }
            }

            $uploaded = [];
            foreach ($normalizedFiles as $index => $file) {
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