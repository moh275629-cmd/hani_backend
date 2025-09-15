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
        $request->validate([
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

        $userQuery = User::query();
        if ($request->filled('email')) {
            $userQuery->where('email', $request->string('email'));
        } elseif ($request->filled('phone')) {
            $userQuery->where('phone', $request->string('phone'));
        }
        $user = $userQuery->first();

        if (!$user || !Hash::check($request->string('password'), $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

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
                'user_id' => $user->id,
            ];
        }

        return response()->json([
            'message' => 'Documents uploaded successfully',
            'data' => $uploaded,
        ]);
    }
 }
 

