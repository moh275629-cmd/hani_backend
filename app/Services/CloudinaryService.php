<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    protected $cloudinary;

    public function __construct()
    {
        // Configure Cloudinary using the direct PHP package
        Configuration::instance([
            'cloud' => [
                'cloud_name' => config('cloudinary.cloud_name'),
                'api_key' => config('cloudinary.api_key'),
                'api_secret' => config('cloudinary.api_secret'),
            ],
            'url' => [
                'secure' => config('cloudinary.secure', true),
            ],
        ]);

        $this->cloudinary = new Cloudinary();
    }

    /**
     * Upload a file to Cloudinary
     *
     * @param UploadedFile $file
     * @param string $folder
     * @param array $options
     * @return array
     */
    public function uploadFile(UploadedFile $file, string $folder = 'hani', array $options = []): array
    {
        try {
            // Check if Cloudinary is properly configured
            $cloudName = config('cloudinary.cloud_name');
            $apiKey = config('cloudinary.api_key');
            $apiSecret = config('cloudinary.api_secret');
            
            if (empty($cloudName) || empty($apiKey) || empty($apiSecret)) {
                throw new \Exception('Cloudinary configuration is missing. Please check your .env file.');
            }

            $defaultOptions = [
                'folder' => $folder,
                'resource_type' => 'auto', // Automatically detect image, video, or raw
                'quality' => 'auto',
                'fetch_format' => 'auto',
            ];

            $uploadOptions = array_merge($defaultOptions, $options);

            Log::info('Uploading to Cloudinary', [
                'file_path' => $file->getRealPath(),
                'file_size' => $file->getSize(),
                'file_mime' => $file->getMimeType(),
                'options' => $uploadOptions
            ]);

            // Try using file content instead of file path
            $fileContent = file_get_contents($file->getRealPath());
            if ($fileContent === false) {
                throw new \Exception('Could not read file content');
            }
            
            $result = $this->cloudinary->uploadApi()->upload($fileContent, $uploadOptions);

            // Check if result is null or empty
            if (empty($result) || !is_array($result)) {
                Log::error('Cloudinary upload returned null or invalid result', [
                    'result' => $result,
                    'result_type' => gettype($result)
                ]);
                throw new \Exception('Cloudinary upload returned null or invalid result');
            }

            Log::info('Cloudinary upload successful', ['result' => $result]);

            return [
                'success' => true,
                'public_id' => $result['public_id'] ?? null,
                'secure_url' => $result['secure_url'] ?? null,
                'url' => $result['url'] ?? null,
                'format' => $result['format'] ?? null,
                'resource_type' => $result['resource_type'] ?? null,
                'bytes' => $result['bytes'] ?? null,
                'width' => $result['width'] ?? null,
                'height' => $result['height'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Cloudinary upload failed: ' . $e->getMessage(), [
                'file_path' => $file->getRealPath(),
                'file_size' => $file->getSize(),
                'file_mime' => $file->getMimeType(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload multiple files to Cloudinary
     *
     * @param array $files
     * @param string $folder
     * @param array $options
     * @return array
     */
    public function uploadMultipleFiles(array $files, string $folder = 'hani', array $options = []): array
    {
        $results = [];
        $successCount = 0;

        foreach ($files as $index => $file) {
            if ($file instanceof UploadedFile) {
                $result = $this->uploadFile($file, $folder, $options);
                $results[$index] = $result;
                
                if ($result['success']) {
                    $successCount++;
                }
            }
        }

        return [
            'success' => $successCount > 0,
            'uploaded_count' => $successCount,
            'total_count' => count($files),
            'results' => $results,
        ];
    }

    /**
     * Upload a PDF document to Cloudinary
     *
     * @param UploadedFile $file
     * @param string $folder
     * @return array
     */
    public function uploadDocument(UploadedFile $file, string $folder = 'hani/documents'): array
    {
        $options = [
            'resource_type' => 'raw',
            'format' => 'pdf',
        ];

        return $this->uploadFile($file, $folder, $options);
    }

    /**
     * Upload store images to Cloudinary
     *
     * @param UploadedFile $file
     * @param int $storeId
     * @param bool $isMainImage
     * @return array
     */
    public function uploadStoreImage(UploadedFile $file, int $storeId, bool $isMainImage = false): array
    {
        $folder = "hani/stores/{$storeId}";
        $options = [
            'resource_type' => 'image',
            'transformation' => [
                ['width' => 800, 'height' => 600, 'crop' => 'fill', 'quality' => 'auto'],
            ],
        ];

        if ($isMainImage) {
            $options['public_id'] = "main_image";
        }

        return $this->uploadFile($file, $folder, $options);
    }

    /**
     * Upload offer images/videos to Cloudinary
     *
     * @param UploadedFile $file
     * @param int $offerId
     * @param bool $isMainImage
     * @return array
     */
    public function uploadOfferMedia(UploadedFile $file, int $offerId, bool $isMainImage = false): array
    {
        $folder = "hani/offers/{$offerId}";
        $options = [
            'resource_type' => 'auto',
        ];

        if ($isMainImage) {
            $options['public_id'] = "main_media";
            $options['transformation'] = [
                ['width' => 400, 'height' => 300, 'crop' => 'fill', 'quality' => 'auto'],
            ];
        }

        return $this->uploadFile($file, $folder, $options);
    }

    /**
     * Delete a file from Cloudinary
     *
     * @param string $publicId
     * @param string $resourceType
     * @return array
     */
    public function deleteFile(string $publicId, string $resourceType = 'image'): array
    {
        try {
            $result = $this->uploadApi->destroy($publicId, [
                'resource_type' => $resourceType,
            ]);

            return [
                'success' => true,
                'result' => $result,
            ];
        } catch (\Exception $e) {
            Log::error('Cloudinary delete failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get file info from Cloudinary
     *
     * @param string $publicId
     * @param string $resourceType
     * @return array
     */
    public function getFileInfo(string $publicId, string $resourceType = 'image'): array
    {
        try {
            $result = $this->uploadApi->resource($publicId, [
                'resource_type' => $resourceType,
            ]);

            return [
                'success' => true,
                'info' => $result,
            ];
        } catch (\Exception $e) {
            Log::error('Cloudinary get info failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate a secure URL for a Cloudinary resource
     *
     * @param string $publicId
     * @param array $transformations
     * @param string $resourceType
     * @return string
     */
    public function getSecureUrl(string $publicId, array $transformations = [], string $resourceType = 'image'): string
    {
        return $this->cloudinary->image($publicId)
            ->resize($transformations)
            ->secure()
            ->toUrl();
    }
}
