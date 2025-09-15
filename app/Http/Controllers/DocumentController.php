 <?php
 
 namespace App\Http\Controllers;
 
 use App\Models\Document;
 use App\Models\User;
 use Illuminate\Http\Request;
 use Illuminate\Support\Facades\Storage;
 use Illuminate\Http\JsonResponse;
 use Illuminate\Support\Facades\Validator;
 use Illuminate\Support\Facades\Hash;
 
 class DocumentController extends Controller
 {
     public function upload(Request $request, $userId): JsonResponse
     {
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
     }
 
     public function listByUser($userId): JsonResponse
     {
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
     }

    public function uploadByCredentials(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required_without:phone|email',
                'phone' => 'required_without:email|string',
                'password' => 'required|string',
                'documents' => 'required|array',
                'documents.*' => 'file|mimes:jpg,jpeg,png,pdf|max:20480',
                'names' => 'array',
                'names.*' => 'string|nullable',
                'descriptions' => 'array',
                'descriptions.*' => 'string|nullable',
            ]);
            

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Resolve user by email or phone
            $userQuery = User::query();
            $email = (string) $request->input('email', '');
            $phone = (string) $request->input('phone', '');
            if (!empty($email)) {
                $userQuery->where('email', $email);
            } else {
                $userQuery->where('phone', $phone);
            }
            $user = $userQuery->first();

            if (!$user || !Hash::check((string) $request->input('password', ''), $user->password)) {
                return response()->json([
                    'message' => 'Invalid credentials',
                ], 401);
            }

            // Normalize documents to an array of UploadedFile
            $files = $request->file('documents');
            if ($files === null) {
                return response()->json([
                    'message' => 'No documents found in request',
                ], 400);
            }
            if (!is_array($files)) {
                $files = [$files];
            }

            // Validate each file
            foreach ($files as $f) {
                if (!$f->isValid()) {
                    return response()->json([
                        'message' => 'One or more files are invalid',
                    ], 400);
                }
                // Extra mime/size validation layer
                $mime = $f->getClientMimeType();
                $sizeKb = (int) ceil($f->getSize() / 1024);
                if (!in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'])) {
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
            foreach ($files as $index => $file) {
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
                    'user_id' => $user->id,
                ];
            }

            return response()->json([
                'message' => 'Documents uploaded successfully',
                'data' => $uploaded,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error uploading documents',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
 }
 

