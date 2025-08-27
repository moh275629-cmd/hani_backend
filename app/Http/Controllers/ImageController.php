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
                
                // Check if this is a multipart boundary issue
                $contentType = $request->header('Content-Type');
                if (strpos($contentType, 'multipart/form-data') !== false) {
                    \Log::warning('Multipart request detected but no files found - possible boundary issue');
                    \Log::warning('Content-Type: ' . $contentType);
                    \Log::warning('Request body preview: ' . substr($request->getContent(), 0, 200));
                }
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
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
            $mimeType = $imageFile->getMimeType();
            
            if (!in_array($mimeType, $allowedTypes)) {
                \Log::error('File type validation failed: ' . $mimeType . ' not in ' . json_encode($allowedTypes));
                return response()->json([
                    'message' => 'Invalid file type. Allowed: JPEG, PNG, JPG, GIF, WEBP',
                    'errors' => ['image' => ['Invalid file type. Allowed: JPEG, PNG, JPG, GIF, WEBP']]
                ], 422);
            }
            $imageData = file_get_contents($imageFile->getRealPath());
            
            // Generate a temporary ID
            $tempId = 'temp_' . uniqid();
            
            // Determine file extension based on MIME type
            $mimeType = $imageFile->getMimeType();
            $extension = 'jpg'; // default
            
            if ($mimeType === 'image/png') {
                $extension = 'png';
            } elseif ($mimeType === 'image/gif') {
                $extension = 'gif';
            } elseif ($mimeType === 'image/webp') {
                $extension = 'webp';
            }
            
            // Store in temporary file with proper extension
            $tempPath = storage_path('app/temp/' . $tempId . '.' . $extension);
            
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
            
            // Check file type manually - accept all common image formats
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/bmp'];
            $mimeType = $imageFile->getMimeType();
            
            if (!in_array($mimeType, $allowedTypes)) {
                \Log::error('File type validation failed: ' . $mimeType . ' not in ' . json_encode($allowedTypes));
                return response()->json([
                    'message' => 'Invalid file type. Allowed: JPEG, PNG, JPG, GIF, WEBP, BMP',
                    'errors' => ['image' => ['Invalid file type. Allowed: JPEG, PNG, JPG, GIF, WEBP, BMP']]
                ], 422);
            }
            
            $imageData = file_get_contents($imageFile->getRealPath());
            
            // Generate a temporary ID
            $tempId = 'temp_logo_' . uniqid();
            
            // Determine file extension based on MIME type
            $extension = 'jpg'; // default
            
            if ($mimeType === 'image/png') {
                $extension = 'png';
            } elseif ($mimeType === 'image/gif') {
                $extension = 'gif';
            } elseif ($mimeType === 'image/webp') {
                $extension = 'webp';
            } elseif ($mimeType === 'image/bmp') {
                $extension = 'bmp';
            }
            
            // Store in temporary file with proper extension
            $tempPath = storage_path('app/temp/' . $tempId . '.' . $extension);
            
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
                    'file_size' => file_exists($tempPath) ? filesize($tempPath) : 0,
                    'mime_type' => $mimeType,
                    'extension' => $extension
                ]
            ]);
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
        try {
            \Log::info('uploadTempStoreBanner called');
            \Log::info('Request has files: ' . $request->hasFile('image'));
            \Log::info('Request files count: ' . count($request->allFiles()));
            \Log::info('Request content type: ' . $request->header('Content-Type'));
            
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
            
            // Check file type manually - accept all common image formats
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/bmp'];
            $mimeType = $imageFile->getMimeType();
            
            if (!in_array($mimeType, $allowedTypes)) {
                \Log::error('File type validation failed: ' . $mimeType . ' not in ' . json_encode($allowedTypes));
                return response()->json([
                    'message' => 'Invalid file type. Allowed: JPEG, PNG, JPG, GIF, WEBP, BMP',
                    'errors' => ['image' => ['Invalid file type. Allowed: JPEG, PNG, JPG, GIF, WEBP, BMP']]
                ], 422);
            }
            
            $imageData = file_get_contents($imageFile->getRealPath());
            
            // Generate a temporary ID
            $tempId = 'temp_banner_' . uniqid();
            
            // Determine file extension based on MIME type
            $extension = 'jpg'; // default
            
            if ($mimeType === 'image/png') {
                $extension = 'png';
            } elseif ($mimeType === 'image/gif') {
                $extension = 'gif';
            } elseif ($mimeType === 'image/webp') {
                $extension = 'webp';
            } elseif ($mimeType === 'image/bmp') {
                $extension = 'bmp';
            }
            
            // Store in temporary file with proper extension
            $tempPath = storage_path('app/temp/' . $tempId . '.' . $extension);
            
            // Ensure temp directory exists
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }
            
            // Save the image to temporary file
            file_put_contents($tempPath, $imageData);
            
            return response()->json([
                'message' => 'Temporary store banner uploaded successfully',
                'temp_id' => $tempId,
                'image_url' => "/api/images/temp/{$tempId}",
                'debug' => [
                    'temp_path' => $tempPath,
                    'file_exists' => file_exists($tempPath),
                    'data_size' => strlen($imageData),
                    'file_size' => file_exists($tempPath) ? filesize($tempPath) : 0,
                    'mime_type' => $mimeType,
                    'extension' => $extension
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Store banner upload error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'The store banner failed to upload.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Serve temporary image
     */
    public function serveTempImage($tempId)
    {
        \Log::info('serveTempImage called with tempId: ' . $tempId);
        
        // Try to find the temporary file with any extension
        $tempDir = storage_path('app/temp/');
        $tempFiles = glob($tempDir . $tempId . '.*');
        
        \Log::info('Temp directory: ' . $tempDir);
        \Log::info('Found temp files: ' . json_encode($tempFiles));
        
        if (empty($tempFiles)) {
            \Log::error('No temporary files found for tempId: ' . $tempId);
            return response()->json(['error' => 'Temporary image not found', 'temp_id' => $tempId, 'path' => $tempDir], 404);
        }
        
        $tempPath = $tempFiles[0]; // Use the first matching file
        \Log::info('Using temp file: ' . $tempPath);
        
        if (!file_exists($tempPath)) {
            \Log::error('Temp file does not exist: ' . $tempPath);
            return response()->json(['error' => 'Temporary image file does not exist', 'temp_id' => $tempId, 'path' => $tempPath], 404);
        }
        
        $imageData = file_get_contents($tempPath);
        
        if (!$imageData) {
            \Log::error('Temp image data is empty for tempId: ' . $tempId);
            return response()->json(['error' => 'Temporary image data is empty', 'temp_id' => $tempId], 404);
        }
        
        \Log::info('Image data loaded successfully - Size: ' . strlen($imageData) . ' bytes');
        
        // Try to detect image type from the data
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $imageData);
        finfo_close($finfo);
        
        // If we can't detect the type, default to jpeg
        if (!$mimeType || !str_starts_with($mimeType, 'image/')) {
            $mimeType = 'image/jpeg';
        }
        
        \Log::info('Detected MIME type: ' . $mimeType);
        
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
            
            // Try to find the temporary file with any extension
            $tempDir = storage_path('app/temp/');
            $tempFiles = glob($tempDir . $tempId . '.*');
            
            if (empty($tempFiles)) {
                \Log::error('Temporary image file not found for temp_id: ' . $tempId);
                return response()->json(['error' => 'Temporary image file not found', 'temp_id' => $tempId, 'path' => $tempDir], 404);
            }
            
            $tempPath = $tempFiles[0]; // Use the first matching file
            
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
     * Upload profile image directly (for client users - no admin approval needed)
     */
    public function uploadProfileImage(Request $request)
    {
        try {
            \Log::info('uploadProfileImage called');
            \Log::info('Request has files: ' . $request->hasFile('image'));
            \Log::info('Request files count: ' . count($request->allFiles()));
            \Log::info('Request content type: ' . $request->header('Content-Type'));
            
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
            
            // Check file type manually - accept all common image formats
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/bmp'];
            $mimeType = $imageFile->getMimeType();
            
            if (!in_array($mimeType, $allowedTypes)) {
                \Log::error('File type validation failed: ' . $mimeType . ' not in ' . json_encode($allowedTypes));
                return response()->json([
                    'message' => 'Invalid file type. Allowed: JPEG, PNG, JPG, GIF, WEBP, BMP',
                    'errors' => ['image' => ['Invalid file type. Allowed: JPEG, PNG, JPG, GIF, WEBP, BMP']]
                ], 422);
            }
            
            $imageData = file_get_contents($imageFile->getRealPath());
            
            // Get the authenticated user
            $user = auth()->user();
            \Log::info('User found: ' . $user->id . ' - ' . $user->email . ' - Role: ' . $user->role);
            
            // Set the profile image blob
            $user->setProfileImageBlob($imageData);
            \Log::info('setProfileImageBlob called successfully');
            
            // Also set the profile_image field with the URL
            $user->profile_image = "/api/images/user/{$user->id}/profile";
            \Log::info('profile_image URL set to: ' . $user->profile_image);
            
            // Save the user
            $saved = $user->save();
            \Log::info('User save result: ' . ($saved ? 'true' : 'false'));
            
            // Verify the image was saved
            $user->refresh();
            $finalBlobSize = $user->profile_image_blob ? strlen($user->profile_image_blob) : 0;
            \Log::info('Final profile_image_blob size: ' . $finalBlobSize);
            \Log::info('Final profile_image URL: ' . $user->profile_image);
            \Log::info('User profile_image field value: ' . ($user->profile_image ?? 'null'));
            
            if ($finalBlobSize === 0) {
                \Log::error('Profile image blob was not saved properly');
                return response()->json([
                    'error' => 'Failed to save profile image to database',
                    'image_size' => strlen($imageData)
                ], 500);
            }
            
            return response()->json([
                'message' => 'Profile image uploaded successfully',
                'user_id' => $user->id,
                'image_url' => "/api/images/user/{$user->id}/profile",
                'debug' => [
                    'image_size' => strlen($imageData),
                    'final_blob_size' => $finalBlobSize,
                    'profile_image_url' => $user->profile_image,
                    'mime_type' => $mimeType,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Profile image upload error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'The profile image failed to upload.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload temporary profile image
     */
    public function uploadTempProfileImage(Request $request)
    {
        try {
            \Log::info('uploadTempProfileImage called');
            \Log::info('Request has files: ' . $request->hasFile('image'));
            \Log::info('Request files count: ' . count($request->allFiles()));
            \Log::info('Request content type: ' . $request->header('Content-Type'));
            
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
            
            // Check file type manually - accept all common image formats
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/bmp'];
            $mimeType = $imageFile->getMimeType();
            
            if (!in_array($mimeType, $allowedTypes)) {
                \Log::error('File type validation failed: ' . $mimeType . ' not in ' . json_encode($allowedTypes));
                return response()->json([
                    'message' => 'Invalid file type. Allowed: JPEG, PNG, JPG, GIF, WEBP, BMP',
                    'errors' => ['image' => ['Invalid file type. Allowed: JPEG, PNG, JPG, GIF, WEBP, BMP']]
                ], 422);
            }
            
        $imageData = file_get_contents($imageFile->getRealPath());
        
        // Generate a temporary ID
        $tempId = 'temp_profile_' . uniqid();
        
        // Determine file extension based on MIME type
        $extension = 'jpg'; // default
        
        if ($mimeType === 'image/png') {
            $extension = 'png';
        } elseif ($mimeType === 'image/gif') {
            $extension = 'gif';
        } elseif ($mimeType === 'image/webp') {
            $extension = 'webp';
            } elseif ($mimeType === 'image/bmp') {
                $extension = 'bmp';
        }
        
        // Store in temporary file with proper extension
        $tempPath = storage_path('app/temp/' . $tempId . '.' . $extension);
        
        // Ensure temp directory exists
        if (!file_exists(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }
        
        // Save the image to temporary file
        file_put_contents($tempPath, $imageData);
        
        return response()->json([
            'message' => 'Temporary profile image uploaded successfully',
            'temp_id' => $tempId,
                'image_url' => "/api/images/temp/{$tempId}",
                'debug' => [
                    'temp_path' => $tempPath,
                    'file_exists' => file_exists($tempPath),
                    'data_size' => strlen($imageData),
                    'file_size' => file_exists($tempPath) ? filesize($tempPath) : 0,
                    'mime_type' => $mimeType,
                    'extension' => $extension
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Profile image upload error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'The profile image failed to upload.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Move temporary profile image to permanent profile image
     */
    public function moveTempProfileImage(Request $request)
    {
        try {
            \Log::info('moveTempProfileImage called');
            \Log::info('Request data: ' . json_encode($request->all()));
            
            $request->validate([
                'temp_id' => 'required|string'
            ]);

            $tempId = $request->input('temp_id');
            \Log::info('Temp ID: ' . $tempId);
            
            // Try to find the temporary file with any extension
            $tempDir = storage_path('app/temp/');
            $tempFiles = glob($tempDir . $tempId . '.*');
            
            if (empty($tempFiles)) {
                \Log::error('Temporary image file not found for temp_id: ' . $tempId);
                return response()->json(['error' => 'Temporary image file not found', 'temp_id' => $tempId, 'path' => $tempDir], 404);
            }
            
            $tempPath = $tempFiles[0]; // Use the first matching file
            \Log::info('Found temporary file: ' . $tempPath);
            
            // Get the temporary image from file
            if (!file_exists($tempPath)) {
                \Log::error('Temporary image file does not exist: ' . $tempPath);
                return response()->json(['error' => 'Temporary image file not found', 'temp_id' => $tempId], 404);
            }
            
            $imageData = file_get_contents($tempPath);
            
            if (!$imageData) {
                \Log::error('Temporary image data is empty for temp_id: ' . $tempId);
                return response()->json(['error' => 'Temporary image data is empty', 'temp_id' => $tempId], 404);
            }

            \Log::info('Image data loaded successfully - Size: ' . strlen($imageData) . ' bytes');

            // Get the authenticated user
            $user = auth()->user();
            \Log::info('User found: ' . $user->id . ' - ' . $user->email);
            
            // Set the profile image blob
            $user->setProfileImageBlob($imageData);
            \Log::info('setProfileImageBlob called successfully');
            
            // Also set the profile_image field with the URL
            $user->profile_image = "/api/images/user/{$user->id}/profile";
            \Log::info('profile_image URL set to: ' . $user->profile_image);
            
            // Save the user
            $saved = $user->save();
            \Log::info('User save result: ' . ($saved ? 'true' : 'false'));
            
            // Verify the image was saved
            $user->refresh();
            $finalBlobSize = $user->profile_image_blob ? strlen($user->profile_image_blob) : 0;
            \Log::info('Final profile_image_blob size: ' . $finalBlobSize);
            \Log::info('Final profile_image URL: ' . $user->profile_image);
            
            if ($finalBlobSize === 0) {
                \Log::error('Profile image blob was not saved properly');
                return response()->json([
                    'error' => 'Failed to save profile image to database',
                    'temp_id' => $tempId,
                    'image_size' => strlen($imageData)
                ], 500);
            }
            
            // Remove the temporary file
            if (file_exists($tempPath)) {
                unlink($tempPath);
                \Log::info('Temporary file deleted: ' . $tempPath);
            }
            
            return response()->json([
                'message' => 'Profile image updated successfully',
                'user_id' => $user->id,
                'image_url' => "/api/images/user/{$user->id}/profile",
                'debug' => [
                    'temp_id' => $tempId,
                    'image_size' => strlen($imageData),
                    'final_blob_size' => $finalBlobSize,
                    'temp_file_deleted' => !file_exists($tempPath),
                    'profile_image_url' => $user->profile_image,
                ]
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
            
            // Try to find the temporary file with any extension
            $tempDir = storage_path('app/temp/');
            $tempFiles = glob($tempDir . $tempId . '.*');
            
            if (empty($tempFiles)) {
                \Log::error('Temporary image file not found for temp_id: ' . $tempId);
                return response()->json(['error' => 'Temporary image file not found', 'temp_id' => $tempId, 'path' => $tempDir], 404);
            }
            
            $tempPath = $tempFiles[0]; // Use the first matching file
            \Log::info('Found temporary file: ' . $tempPath);
            
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
            
            // Debug raw request
            \Log::info('Raw request body length: ' . strlen($request->getContent()));
            \Log::info('Request headers: ' . json_encode($request->headers->all()));
            
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
                // Try alternative methods to detect the file
                \Log::info('Trying alternative file detection methods...');
                
                // Check if there are any files at all
                $allFiles = $request->allFiles();
                \Log::info('All files in request: ' . json_encode($allFiles));
                
                // Check if the file might be in a different field name
                foreach ($allFiles as $fieldName => $file) {
                    \Log::info("Found file in field '$fieldName': " . json_encode([
                        'original_name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                    ]));
                }
                
                return response()->json([
                    'success' => false,
                    'message' => 'No file uploaded',
                    'request_info' => [
                        'has_files' => $request->hasFile('image'),
                        'files_count' => count($request->allFiles()),
                        'content_type' => $request->header('Content-Type'),
                        'content_length' => $request->header('Content-Length'),
                        'all_files' => array_keys($allFiles),
                        'request_data' => $request->all(),
                    ]
                ], 400);
            }
        } catch (\Exception $e) {
            \Log::error('Test upload error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Test upload failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
