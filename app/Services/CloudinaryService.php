<?php

namespace App\Services;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    public function __construct()
    {
        // Cloudinary is configured via config/cloudinary.php
        // and the Laravel package handles the configuration automatically
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
            $defaultOptions = [
                'folder' => $folder,
                'resource_type' => 'auto', // Automatically detect image, video, or raw
                'quality' => 'auto',
                'fetch_format' => 'auto',
            ];

            $uploadOptions = array_merge($defaultOptions, $options);

            $result = Cloudinary::upload($file->getRealPath(), $uploadOptions);

            return [
                'success' => true,
                'public_id' => $result['public_id'],
                'secure_url' => $result['secure_url'],
                'url' => $result['url'],
                'format' => $result['format'],
                'resource_type' => $result['resource_type'],
                'bytes' => $result['bytes'],
                'width' => $result['width'] ?? null,
                'height' => $result['height'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Cloudinary upload failed: ' . $e->getMessage());
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
