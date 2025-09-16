<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class SimpleCloudinaryService
{
    protected $cloudinary;

    public function __construct()
    {
        // Configure Cloudinary using environment variables directly
        Configuration::instance([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key' => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
            ],
            'url' => [
                'secure' => env('CLOUDINARY_SECURE', true),
            ],
        ]);

        $this->cloudinary = new Cloudinary();
    }

    /**
     * Upload a file to Cloudinary
     */
    public function uploadFile(UploadedFile $file, string $folder = 'hani', array $options = []): array
    {
        try {
            Log::info('SimpleCloudinaryService: Starting upload', [
                'file_path' => $file->getRealPath(),
                'file_size' => $file->getSize(),
                'file_mime' => $file->getMimeType(),
                'folder' => $folder,
                'options' => $options
            ]);

            $defaultOptions = [
                'folder' => $folder,
                'resource_type' => 'auto',
                'quality' => 'auto',
                'fetch_format' => 'auto',
            ];

            $uploadOptions = array_merge($defaultOptions, $options);

            $result = $this->cloudinary->uploadApi()->upload($file->getRealPath(), $uploadOptions);

            Log::info('SimpleCloudinaryService: Upload successful', ['result' => $result]);

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
            Log::error('SimpleCloudinaryService: Upload failed', [
                'error' => $e->getMessage(),
                'file_path' => $file->getRealPath(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload a PDF document to Cloudinary
     */
    public function uploadDocument(UploadedFile $file, string $folder = 'hani/documents'): array
    {
        $options = [
            'resource_type' => 'raw',
            'format' => 'pdf',
        ];

        return $this->uploadFile($file, $folder, $options);
    }
}
