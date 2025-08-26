<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ImageController extends Controller
{
    /**
     * Serve store logo image
     */
    public function storeLogo($storeId)
    {
        $store = Store::findOrFail($storeId);
        
        if (!$store->hasLogoBlob()) {
            return response()->json(['error' => 'Logo not found'], 404);
        }

        $imageData = $store->getLogoBlob();
        
        return response($imageData)
            ->header('Content-Type', 'image/jpeg')
            ->header('Cache-Control', 'public, max-age=31536000');
    }

    /**
     * Serve store banner image
     */
    public function storeBanner($storeId)
    {
        $store = Store::findOrFail($storeId);
        
        if (!$store->hasBannerBlob()) {
            return response()->json(['error' => 'Banner not found'], 404);
        }

        $imageData = $store->getBannerBlob();
        
        return response($imageData)
            ->header('Content-Type', 'image/jpeg')
            ->header('Cache-Control', 'public, max-age=31536000');
    }

    /**
     * Serve offer image
     */
    public function offerImage($offerId)
    {
        try {
            \Log::info('offerImage called with offerId: ' . $offerId);
            
            $offer = Offer::findOrFail($offerId);
            \Log::info('Offer found: ' . $offer->id . ', title: ' . $offer->title);
            
            \Log::info('Offer has image_blob field: ' . (isset($offer->image_blob) ? 'yes' : 'no'));
            \Log::info('image_blob size: ' . (isset($offer->image_blob) ? strlen($offer->image_blob) : 'not set'));
            
            if (!$offer->hasImageBlob()) {
                \Log::info('Offer does not have image blob, returning default placeholder');
                
                // Return a default placeholder image instead of 404
                $placeholderPath = public_path('images/placeholder-offer.png');
                
                if (file_exists($placeholderPath)) {
                    $imageData = file_get_contents($placeholderPath);
                    return response($imageData)
                        ->header('Content-Type', 'image/png')
                        ->header('Cache-Control', 'public, max-age=31536000');
                } else {
                    // If no placeholder exists, return a simple 1x1 transparent PNG
                    $transparentPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
                    return response($transparentPng)
                        ->header('Content-Type', 'image/png')
                        ->header('Cache-Control', 'public, max-age=31536000');
                }
            }

            $imageData = $offer->getImageBlob();
            \Log::info('Retrieved image data size: ' . strlen($imageData));
            
            if (empty($imageData)) {
                \Log::warning('Image data is empty after retrieval');
                return response()->json(['error' => 'Image data is empty'], 404);
            }
            
            return response($imageData)
                ->header('Content-Type', 'image/jpeg')
                ->header('Cache-Control', 'public, max-age=31536000');
        } catch (\Exception $e) {
            \Log::error('Error in offerImage: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'error' => 'Failed to retrieve image',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload store logo
     */
    public function uploadStoreLogo(Request $request, $storeId)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:10240'
        ]);

        $store = Store::findOrFail($storeId);
        
        $imageFile = $request->file('image');
        $imageData = file_get_contents($imageFile->getRealPath());
        
        $store->setLogoBlob($imageData);
        
        return response()->json([
            'message' => 'Logo uploaded successfully',
            'store_id' => $storeId,
            'image_url' => "/api/images/store/{$storeId}/logo"
        ]);
    }

    /**
     * Upload store banner
     */
    public function uploadStoreBanner(Request $request, $storeId)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:10240'
        ]);

        $store = Store::findOrFail($storeId);
        
        $imageFile = $request->file('image');
        $imageData = file_get_contents($imageFile->getRealPath());
        
        $store->setBannerBlob($imageData);
        
        return response()->json([
            'message' => 'Banner uploaded successfully',
            'store_id' => $storeId,
            'image_url' => "/api/images/store/{$storeId}/banner"
        ]);
    }

    /**
     * Upload offer image
     */
    public function uploadOfferImage(Request $request, $offerId)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:10240'
        ]);

        $offer = Offer::findOrFail($offerId);
        
        $imageFile = $request->file('image');
        $imageData = file_get_contents($imageFile->getRealPath());
        
        $offer->setImageBlob($imageData);
        
        return response()->json([
            'message' => 'Offer image uploaded successfully',
            'offer_id' => $offerId,
            'image_url' => "/api/images/offer/{$offerId}"
        ]);
    }

    /**
     * Upload temporary offer image (for new offers)
     */
    public function uploadTempOfferImage(Request $request)
    {
        try {
            \Log::info('uploadTempOfferImage called');
            \Log::info('Request has files: ' . $request->hasFile('image'));
            \Log::info('Request files count: ' . count($request->allFiles()));
            \Log::info('Request content type: ' . $request->header('Content-Type'));
            \Log::info('Request content length: ' . $request->header('Content-Length'));
            \Log::info('Request method: ' . $request->method());
            \Log::info('Request URL: ' . $request->url());
            
            // Debug all request data
            \Log::info('All request data: ' . json_encode($request->all()));
            \Log::info('All files: ' . json_encode($request->allFiles()));
            \Log::info('Request input: ' . json_encode($request->input()));
            
            // Debug PHP configuration
            \Log::info('PHP upload_max_filesize: ' . ini_get('upload_max_filesize'));
            \Log::info('PHP post_max_size: ' . ini_get('post_max_size'));
            \Log::info('PHP max_file_uploads: ' . ini_get('max_file_uploads'));
            
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                \Log::info('File details: ' . json_encode([
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'extension' => $file->getClientOriginalExtension(),
                    'is_valid' => $file->isValid(),
                    'error' => $file->getError(),
                ]));
            } else {
                \Log::error('No image file detected in request');
                \Log::error('Request files: ' . json_encode($request->allFiles()));
                \Log::error('Request input: ' . json_encode($request->input()));
            }
            
            // Temporarily comment out validation to test file upload
            // $request->validate([
            //     'image' => 'required|image|mimes:jpeg,png,jpg|max:5120'
            // ]);

            // Manual validation
            if (!$request->hasFile('image')) {
                \Log::error('Manual validation failed: No image file uploaded');
                return response()->json([
                    'message' => 'No image file was uploaded',
                    'errors' => ['image' => ['No image file was uploaded']],
                    'debug' => [
                        'has_file' => $request->hasFile('image'),
                        'files_count' => count($request->allFiles()),
                        'content_type' => $request->header('Content-Type'),
                        'content_length' => $request->header('Content-Length'),
                        'all_files' => $request->allFiles(),
                        'all_input' => $request->input(),
                    ]
                ], 422);
            }

            $imageFile = $request->file('image');
            
            // Debug file details
            \Log::info('Manual validation - File size: ' . $imageFile->getSize() . ' bytes');
            \Log::info('Manual validation - MIME type: ' . $imageFile->getMimeType());
            \Log::info('Manual validation - Extension: ' . $imageFile->getClientOriginalExtension());
            
            // Check file size manually
            $fileSize = $imageFile->getSize();
            $maxSize = 10 * 1024 * 1024; // 10MB in bytes
            
            if ($fileSize > $maxSize) {
                \Log::error('File size validation failed: ' . $fileSize . ' > ' . $maxSize);
                return response()->json([
                    'message' => 'File size too large. Maximum allowed: 10MB',
                    'errors' => ['image' => ['File size too large. Maximum allowed: 10MB']]
                ], 422);
            }
            
            // Check file type manually
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            $mimeType = $imageFile->getMimeType();
            
            if (!in_array($mimeType, $allowedTypes)) {
                \Log::error('File type validation failed: ' . $mimeType . ' not in ' . json_encode($allowedTypes));
                return response()->json([
                    'message' => 'Invalid file type. Allowed: JPEG, PNG, JPG',
                    'errors' => ['image' => ['Invalid file type. Allowed: JPEG, PNG, JPG']]
                ], 422);
            }
            $imageData = file_get_contents($imageFile->getRealPath());
            
            // Generate a temporary ID
            $tempId = 'temp_' . uniqid();
            
            // Store in temporary file instead of session
            $tempPath = storage_path('app/temp/' . $tempId . '.jpg');
            
            // Ensure temp directory exists
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }
            
            // Save the image to temporary file
            file_put_contents($tempPath, $imageData);
            
            return response()->json([
                'message' => 'Temporary offer image uploaded successfully',
                'temp_id' => $tempId,
                'image_url' => "/api/images/temp/{$tempId}",
                'debug' => [
                    'temp_path' => $tempPath,
                    'file_exists' => file_exists($tempPath),
                    'data_size' => strlen($imageData),
                    'file_size' => file_exists($tempPath) ? filesize($tempPath) : 0
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Image upload validation failed: ' . json_encode($e->errors()));
            return response()->json([
                'message' => 'The image failed to upload.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Image upload error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'The image failed to upload.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload temporary store logo (for new stores)
     */
    public function uploadTempStoreLogo(Request $request)
    {
        try {
            \Log::info('uploadTempStoreLogo called');
            \Log::info('Request has files: ' . $request->hasFile('image'));
            \Log::info('Request files count: ' . count($request->allFiles()));
            \Log::info('Request content type: ' . $request->header('Content-Type'));
            
            // Debug all request data
            \Log::info('All request data: ' . json_encode($request->all()));
            \Log::info('All files: ' . json_encode($request->allFiles()));
            
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                \Log::info('File details: ' . json_encode([
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'extension' => $file->getClientOriginalExtension(),
                    'is_valid' => $file->isValid(),
                    'error' => $file->getError(),
                ]));
            } else {
                \Log::error('No image file detected in request');
                return response()->json([
                    'message' => 'No image file provided',
                    'errors' => ['image' => ['The image field is required.']]
                ], 422);
            }

            $imageFile = $request->file('image');
            
            // Debug file details
            \Log::info('Manual validation - File size: ' . $imageFile->getSize() . ' bytes');
            \Log::info('Manual validation - MIME type: ' . $imageFile->getMimeType());
            \Log::info('Manual validation - Extension: ' . $imageFile->getClientOriginalExtension());
            
            // Check file size manually
            $fileSize = $imageFile->getSize();
            $maxSize = 10 * 1024 * 1024; // 10MB in bytes
            
            if ($fileSize > $maxSize) {
                \Log::error('File size validation failed: ' . $fileSize . ' > ' . $maxSize);
                return response()->json([
                    'message' => 'File size too large. Maximum allowed: 10MB',
                    'errors' => ['image' => ['File size too large. Maximum allowed: 10MB']]
                ], 422);
            }
            
            // Check file type manually
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            $mimeType = $imageFile->getMimeType();
            
            if (!in_array($mimeType, $allowedTypes)) {
                \Log::error('File type validation failed: ' . $mimeType . ' not in ' . json_encode($allowedTypes));
                return response()->json([
                    'message' => 'Invalid file type. Allowed: JPEG, PNG, JPG',
                    'errors' => ['image' => ['Invalid file type. Allowed: JPEG, PNG, JPG']]
                ], 422);
            }
            
            $imageData = file_get_contents($imageFile->getRealPath());
            
            // Generate a temporary ID
            $tempId = 'temp_logo_' . uniqid();
            
            // Store in temporary file instead of session
            $tempPath = storage_path('app/temp/' . $tempId . '.jpg');
            
            // Ensure temp directory exists
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }
            
            // Save the image to temporary file
            file_put_contents($tempPath, $imageData);
            
            return response()->json([
                'message' => 'Temporary store logo uploaded successfully',
                'temp_id' => $tempId,
                'image_url' => "/api/images/temp/{$tempId}",
                'debug' => [
                    'temp_path' => $tempPath,
                    'file_exists' => file_exists($tempPath),
                    'data_size' => strlen($imageData),
                    'file_size' => file_exists($tempPath) ? filesize($tempPath) : 0
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Store logo upload validation failed: ' . json_encode($e->errors()));
            return response()->json([
                'message' => 'The store logo failed to upload.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Store logo upload error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'The store logo failed to upload.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload temporary store banner (for new stores)
     */
    public function uploadTempStoreBanner(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:10240'
        ]);

        $imageFile = $request->file('image');
        $imageData = file_get_contents($imageFile->getRealPath());
        
        // Generate a temporary ID
        $tempId = 'temp_banner_' . uniqid();
        
        // Store in temporary file instead of session
        $tempPath = storage_path('app/temp/' . $tempId . '.jpg');
        
        // Ensure temp directory exists
        if (!file_exists(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }
        
        // Save the image to temporary file
        file_put_contents($tempPath, $imageData);
        
        return response()->json([
            'message' => 'Temporary store banner uploaded successfully',
            'temp_id' => $tempId,
            'image_url' => "/api/images/temp/{$tempId}"
        ]);
    }

    /**
     * Serve temporary image
     */
    public function serveTempImage($tempId)
    {
        $tempPath = storage_path('app/temp/' . $tempId . '.jpg');
        
        if (!file_exists($tempPath)) {
            return response()->json(['error' => 'Temporary image not found', 'temp_id' => $tempId, 'path' => $tempPath], 404);
        }
        
        $imageData = file_get_contents($tempPath);
        
        if (!$imageData) {
            return response()->json(['error' => 'Temporary image data is empty', 'temp_id' => $tempId], 404);
        }
        
        // Try to detect image type from the data
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $imageData);
        finfo_close($finfo);
        
        // If we can't detect the type, default to jpeg
        if (!$mimeType || !str_starts_with($mimeType, 'image/')) {
            $mimeType = 'image/jpeg';
        }
        
        return response($imageData)
            ->header('Content-Type', $mimeType)
            ->header('Cache-Control', 'public, max-age=3600'); // 1 hour cache for temp images
    }

    /**
     * Move temporary offer image to permanent offer image
     */
    public function moveTempOfferImage(Request $request, $offerId)
    {
        try {
            \Log::info('moveTempOfferImage called with offerId: ' . $offerId);
            \Log::info('Request data: ' . json_encode($request->all()));
            
            $request->validate([
                'temp_id' => 'required|string'
            ]);

            $tempId = $request->input('temp_id');
            \Log::info('Temp ID: ' . $tempId);
            
            $tempPath = storage_path('app/temp/' . $tempId . '.jpg');
            
            // Get the temporary image from file
            if (!file_exists($tempPath)) {
                \Log::error('Temporary image file not found: ' . $tempPath);
                return response()->json(['error' => 'Temporary image file not found', 'temp_id' => $tempId, 'path' => $tempPath], 404);
            }
            
            $imageData = file_get_contents($tempPath);
            
            if (!$imageData) {
                \Log::error('Temporary image data is empty for temp_id: ' . $tempId);
                return response()->json(['error' => 'Temporary image data is empty', 'temp_id' => $tempId], 404);
            }
            
            // Ensure we're working with binary data, not text
            if (!is_string($imageData)) {
                \Log::error('Temporary image data is not a string: ' . gettype($imageData));
                return response()->json(['error' => 'Invalid image data type', 'temp_id' => $tempId], 500);
            }
            
            \Log::info('Image data loaded successfully - Type: ' . gettype($imageData) . ', Size: ' . strlen($imageData) . ' bytes');

            // Find the offer
            \Log::info('Looking for offer with ID: ' . $offerId);
            $offer = Offer::findOrFail($offerId);
            \Log::info('Found offer: ' . $offer->id);
            
            // Check if the image_blob column exists
            try {
                $dbCheck = \DB::select("DESCRIBE offers");
                $imageBlobColumn = collect($dbCheck)->firstWhere('Field', 'image_blob');
                if (!$imageBlobColumn) {
                    \Log::error('image_blob column does not exist in offers table');
                    return response()->json([
                        'error' => 'Database schema issue: image_blob column not found',
                        'temp_id' => $tempId
                    ], 500);
                }
                \Log::info('image_blob column exists: ' . json_encode($imageBlobColumn));
            } catch (\Exception $e) {
                \Log::error('Error checking database schema: ' . $e->getMessage());
            }
            
            // Set the image blob (this will automatically save)
            try {
                // Ensure we're working with binary data
                if (!is_string($imageData) || empty($imageData)) {
                    throw new \Exception('Invalid image data: ' . gettype($imageData) . ', length: ' . (is_string($imageData) ? strlen($imageData) : 'N/A'));
                }
                
                \Log::info('Image data type: ' . gettype($imageData) . ', length: ' . strlen($imageData));
                
                $offer->setImageBlob($imageData);
                \Log::info('setImageBlob called successfully');
            } catch (\Exception $e) {
                \Log::error('Error in setImageBlob: ' . $e->getMessage());
                \Log::error('Image data info: ' . (is_string($imageData) ? 'String, length: ' . strlen($imageData) : 'Not string: ' . gettype($imageData)));
                return response()->json([
                    'error' => 'Failed to set image blob: ' . $e->getMessage(),
                    'temp_id' => $tempId
                ], 500);
            }
            
            // Verify the image was saved
            $offer->refresh();
            $finalBlobSize = $offer->image_blob ? strlen($offer->image_blob) : 0;
            
            if ($finalBlobSize === 0) {
                \Log::error('Image blob was not saved properly');
                return response()->json([
                    'error' => 'Failed to save image to database',
                    'temp_id' => $tempId,
                    'image_size' => strlen($imageData)
                ], 500);
            }
            
            \Log::info('Image saved successfully, final blob size: ' . $finalBlobSize);
            
            // Remove the temporary file
            if (file_exists($tempPath)) {
                unlink($tempPath);
                \Log::info('Temporary file deleted: ' . $tempPath);
            }
            
            return response()->json([
                'message' => 'Temporary image moved to offer successfully',
                'offer_id' => $offerId,
                'image_url' => "/api/images/offer/{$offerId}",
                'debug' => [
                    'temp_id' => $tempId,
                    'image_size' => strlen($imageData),
                    'final_blob_size' => $finalBlobSize,
                    'temp_file_deleted' => !file_exists($tempPath),
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in moveTempOfferImage: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'error' => 'Failed to move temporary image',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Serve user profile image
     */
    public function userProfileImage($userId)
    {
        $user = User::findOrFail($userId);
        
        if (!$user->hasProfileImageBlob()) {
            return response()->json(['error' => 'Profile image not found'], 404);
        }

        $imageData = $user->getProfileImageBlob();
        
        return response($imageData)
            ->header('Content-Type', 'image/jpeg')
            ->header('Cache-Control', 'public, max-age=31536000');
    }

    /**
     * Upload temporary profile image
     */
    public function uploadTempProfileImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:10240'
        ]);

        $imageFile = $request->file('image');
        $imageData = file_get_contents($imageFile->getRealPath());
        
        // Generate a temporary ID
        $tempId = 'temp_profile_' . uniqid();
        
        // Store in temporary file
        $tempPath = storage_path('app/temp/' . $tempId . '.jpg');
        
        // Ensure temp directory exists
        if (!file_exists(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }
        
        // Save the image to temporary file
        file_put_contents($tempPath, $imageData);
        
        return response()->json([
            'message' => 'Temporary profile image uploaded successfully',
            'temp_id' => $tempId,
            'image_url' => "/api/images/temp/{$tempId}"
        ]);
    }

    /**
     * Move temporary profile image to permanent profile image
     */
    public function moveTempProfileImage(Request $request)
    {
        try {
            $request->validate([
                'temp_id' => 'required|string'
            ]);

            $tempId = $request->input('temp_id');
            $tempPath = storage_path('app/temp/' . $tempId . '.jpg');
            
            // Get the temporary image from file
            if (!file_exists($tempPath)) {
                return response()->json(['error' => 'Temporary image file not found', 'temp_id' => $tempId], 404);
            }
            
            $imageData = file_get_contents($tempPath);
            
            if (!$imageData) {
                return response()->json(['error' => 'Temporary image data is empty', 'temp_id' => $tempId], 404);
            }

            // Get the authenticated user
            $user = auth()->user();
            
            // Set the profile image blob
            $user->setProfileImageBlob($imageData);
            
            // Also set the profile_image field with the URL
            $user->profile_image = "/api/images/user/{$user->id}/profile";
            
            $user->save();
            
            // Remove the temporary file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            
            return response()->json([
                'message' => 'Profile image updated successfully',
                'user_id' => $user->id,
                'image_url' => "/api/images/user/{$user->id}/profile"
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in moveTempProfileImage: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'error' => 'Failed to move temporary profile image',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Move temporary store logo to permanent store logo
     */
    public function moveTempStoreLogo(Request $request, $storeId)
    {
        try {
            \Log::info('moveTempStoreLogo called with storeId: ' . $storeId);
            \Log::info('Request data: ' . json_encode($request->all()));
            
            $request->validate([
                'temp_id' => 'required|string'
            ]);

            $tempId = $request->input('temp_id');
            \Log::info('Temp ID: ' . $tempId);
            
            $tempPath = storage_path('app/temp/' . $tempId . '.jpg');
            
            // Get the temporary image from file
            if (!file_exists($tempPath)) {
                \Log::error('Temporary image file not found: ' . $tempPath);
                return response()->json(['error' => 'Temporary image file not found', 'temp_id' => $tempId, 'path' => $tempPath], 404);
            }
            
            $imageData = file_get_contents($tempPath);
            
            if (!$imageData) {
                \Log::error('Temporary image data is empty for temp_id: ' . $tempId);
                return response()->json(['error' => 'Temporary image data is empty', 'temp_id' => $tempId], 404);
            }

            // Find the store
            \Log::info('Looking for store with ID: ' . $storeId);
            $store = Store::findOrFail($storeId);
            \Log::info('Found store: ' . $store->id);
            
            // Set the logo blob
            $store->setLogoBlob($imageData);
            
            // Save the changes
            $saved = $store->save();
            
            // Remove the temporary file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            
            return response()->json([
                'message' => 'Store logo updated successfully',
                'store_id' => $storeId,
                'image_url' => "/api/images/store/{$storeId}/logo"
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in moveTempStoreLogo: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'error' => 'Failed to move temporary store logo',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test file upload functionality (bypasses validation)
     */
    public function testUpload(Request $request)
    {
        try {
            \Log::info('testUpload called');
            \Log::info('Request has files: ' . $request->hasFile('image'));
            \Log::info('Request files count: ' . count($request->allFiles()));
            \Log::info('Request content type: ' . $request->header('Content-Type'));
            \Log::info('Request content length: ' . $request->header('Content-Length'));
            
            // Debug PHP configuration
            \Log::info('PHP upload_max_filesize: ' . ini_get('upload_max_filesize'));
            \Log::info('PHP post_max_size: ' . ini_get('post_max_size'));
            \Log::info('PHP max_file_uploads: ' . ini_get('max_file_uploads'));
            
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                \Log::info('File details: ' . json_encode([
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'extension' => $file->getClientOriginalExtension(),
                    'is_valid' => $file->isValid(),
                    'error' => $file->getError(),
                ]));
                
                return response()->json([
                    'success' => true,
                    'message' => 'File upload test successful',
                    'file_info' => [
                        'name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                        'extension' => $file->getClientOriginalExtension(),
                        'is_valid' => $file->isValid(),
                        'error' => $file->getError(),
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No file uploaded',
                    'request_info' => [
                        'has_files' => $request->hasFile('image'),
                        'files_count' => count($request->allFiles()),
                        'content_type' => $request->header('Content-Type'),
                        'content_length' => $request->header('Content-Length'),
                    ]
                ], 400);
            }
        } catch (\Exception $e) {
            \Log::error('Test upload error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Test upload failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
