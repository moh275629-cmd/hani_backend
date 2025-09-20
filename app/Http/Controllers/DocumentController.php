<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class DocumentController extends Controller
{
    public function upload(Request $request, $userId): JsonResponse
    {
        try {
            Log::info('Document upload request received', [
                'user_id' => $userId,
                'files_count' => count($request->file('documents', [])),
                'request_data' => $request->except(['password'])
            ]);

            $user = User::findOrFail($userId);

            $request->validate([
                'document_urls' => 'required|array|min:1',
                'document_urls.*' => 'string|url',
                'names' => 'array',
                'names.*' => 'string|nullable',
                'descriptions' => 'array',
                'descriptions.*' => 'string|nullable',
            ]);

            // Reject direct files; URLs only
            if ($request->hasFile('documents')) {
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => ['Direct file uploads are not allowed. Provide Cloudinary PDF URLs only.'],
                ], 422);
            }

            $uploaded = [];

            // Accept direct Cloudinary PDF URLs only
            foreach ($request->input('document_urls', []) as $index => $docUrl) {
                if (!is_string($docUrl) || stripos($docUrl, 'http') !== 0 || !preg_match('/\.pdf(\?|$)/i', $docUrl)) {
                    return response()->json([
                        'message' => 'Validation error',
                        'errors' => ["document_urls.$index must be a PDF url"],
                    ], 422);
                }

                // Enforce Cloudinary host
                $host = parse_url($docUrl, PHP_URL_HOST);
                if (!is_string($host) || stripos($host, 'res.cloudinary.com') === false) {
                    return response()->json([
                        'message' => 'Validation error',
                        'errors' => ["document_urls.$index must be a Cloudinary URL"],
                    ], 422);
                }

                $doc = Document::create([
                    'user_id' => $user->id,
                    'name' => $request->input("names.$index") ?? 'Document',
                    'description' => $request->input("descriptions.$index"),
                    'file_path' => $docUrl,
                ]);

                $uploaded[] = [
                    'id' => $doc->id,
                    'name' => $doc->name,
                    'description' => $doc->description,
                    'file_url' => $doc->file_path,
                    'public_id' => null,
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

    public function view($id): Response
    {
        try {
            $doc = Document::findOrFail($id);
            
            // Optional: Add authentication if needed
            // if (!Auth::check()) {
            //     return response('Unauthorized', 401);
            // }
            
            $client = new Client();
            $response = $client->get($doc->file_path, [
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'application/pdf, */*'
                ]
            ]);
            
            $content = $response->getBody()->getContents();
            $contentType = $response->getHeaderLine('Content-Type') ?: 'application/pdf';
            
            // Get filename from URL or use document name
            $filename = basename(parse_url($doc->file_path, PHP_URL_PATH)) ?: 'document.pdf';
            
            return response($content, 200)
                ->header('Content-Type', $contentType)
                ->header('Content-Disposition', 'inline; filename="' . $filename . '"')
                ->header('Cache-Control', 'public, max-age=3600'); // 1 hour cache

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response('Document not found', 404);
        } catch (RequestException $e) {
            Log::error('Cloudinary request failed: ' . $e->getMessage(), [
                'document_id' => $id,
                'url' => $doc->file_path ?? 'unknown'
            ]);
            return response('Failed to fetch document from storage', 502);
        } catch (\Exception $e) {
            Log::error('Document view error: ' . $e->getMessage(), [
                'document_id' => $id
            ]);
            return response('Internal server error', 500);
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
                        'file_url' => url("/api/documents/{$doc->id}/view"), // Use proxy endpoint
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
                'document_urls' => 'required|array|min:1',
                'document_urls.*' => 'string|url',
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

            $user = User::all()->first(function ($u) use ($request) {
                if (!empty($request->email)) {
                    return strtolower(trim($u->email)) === strtolower(trim($request->email));
                }
                return false;
            });
            
            if (!$user) {
                return response()->json([
                    'message' => 'User not found with the provided email',
                ], 404);
            }
            

            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => 'Invalid password',
                ], 401);
            }

            // Reject any direct files; URLs only
            if ($request->hasFile('documents')) {
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => ['Direct file uploads are not allowed. Provide Cloudinary PDF URLs only.'],
                ], 422);
            }

            $uploaded = [];
            Log::info('uploadByCredentials - payload received', [
                'email' => $request->email,
                'urls_count' => is_array($request->input('document_urls')) ? count($request->input('document_urls')) : 0,
                'has_files' => $request->hasFile('documents')
            ]);

            // Accept direct Cloudinary PDF URLs only
            foreach ($request->input('document_urls', []) as $index => $docUrl) {
                if (!is_string($docUrl) || stripos($docUrl, 'http') !== 0 || !preg_match('/\.pdf(\?|$)/i', $docUrl)) {
                    return response()->json([
                        'message' => 'Validation error',
                        'errors' => ["document_urls.$index must be a PDF url"],
                    ], 422);
                }

                $host = parse_url($docUrl, PHP_URL_HOST);
                if (!is_string($host) || stripos($host, 'res.cloudinary.com') === false) {
                    return response()->json([
                        'message' => 'Validation error',
                        'errors' => ["document_urls.$index must be a Cloudinary URL"],
                    ], 422);
                }

                try {
                    $doc = Document::create([
                        'user_id' => $user->id,
                        'name' => $request->input("names.$index") ?? 'Document',
                        'description' => $request->input("descriptions.$index", ''),
                        'file_path' => $docUrl,
                    ]);

                    $uploaded[] = [
                        'id' => $doc->id,
                        'name' => $doc->name,
                        'description' => $doc->description,
                        'file_url' => url("/api/documents/{$doc->id}/view"), // Use proxy endpoint
                        'public_id' => null,
                        'created_at' => $doc->created_at,
                        'user_id' => $user->id,
                    ];
                } catch (\Exception $ex) {
                    Log::error('Failed saving document URL', [
                        'error' => $ex->getMessage(),
                        'url' => $docUrl,
                        'index' => $index,
                    ]);
                    return response()->json([
                        'message' => 'Failed to save document',
                        'error' => 'Internal server error',
                    ], 500);
                }
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
                'message' => 'Upload failed',
                'error' => 'Internal server error',
            ], 500);
        }
    }

    public function listByCredentials(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required_without:phone|email|nullable',
                'phone' => 'required_without:email|string|nullable',
                'password' => 'required|string',
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

            $user = User::all()->first(function ($u) use ($request) {
                if (!empty($request->email)) {
                    return strtolower(trim($u->email)) === strtolower(trim($request->email));
                }
                return false;
            });

            if (!$user) {
                return response()->json([
                    'message' => 'User not found with the provided email',
                ], 404);
            }

            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => 'Invalid password',
                ], 401);
            }

            $documents = Document::where('user_id', $user->id)
                ->latest()
                ->get()
                ->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'name' => $doc->name,
                        'description' => $doc->description,
                        'file_path' => $doc->file_path,
                        'file_url' => url("/api/documents/{$doc->id}/view"), // Use proxy endpoint
                        'created_at' => $doc->created_at,
                    ];
                });

            return response()->json([
                'message' => 'Documents retrieved successfully',
                'data' => $documents,
            ]);
        } catch (\Exception $e) {
            Log::error('List documents by credentials error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Server Error',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function deleteByCredentials(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required_without:phone|email|nullable',
                'phone' => 'required_without:email|string|nullable',
                'password' => 'required|string',
                'document_id' => 'required|integer',
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

            $user = User::all()->first(function ($u) use ($request) {
                if (!empty($request->email)) {
                    return strtolower(trim($u->email)) === strtolower(trim($request->email));
                }
                return false;
            });

            if (!$user) {
                return response()->json([
                    'message' => 'User not found with the provided email',
                ], 404);
            }

            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => 'Invalid password',
                ], 401);
            }

            $doc = Document::where('id', (int) $request->input('document_id'))
                ->where('user_id', $user->id)
                ->first();

            if (!$doc) {
                return response()->json([
                    'message' => 'Document not found',
                ], 404);
            }

            $doc->delete();

            return response()->json([
                'message' => 'Document deleted successfully',
                'deleted' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Delete document by credentials error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Server Error',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function deleteByUser(Request $request, int $userId, int $documentId): JsonResponse
    {
        try {
            $authUser = Auth::user();
            if (!$authUser || (int)$authUser->id !== (int)$userId) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $doc = Document::where('id', $documentId)
                ->where('user_id', $userId)
                ->first();

            if (!$doc) {
                return response()->json(['message' => 'Document not found'], 404);
            }

            $doc->delete();

            return response()->json([
                'message' => 'Document deleted successfully',
                'deleted' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Delete document by user error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Server Error',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}