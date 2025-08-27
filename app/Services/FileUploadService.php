<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileUploadService
{
    /**
     * Upload a file and return the file path
     */
    public function uploadFile(UploadedFile $file, string $directory = 'uploads', array $allowedTypes = []): array
    {
        try {
            // Validate file type
            if (!empty($allowedTypes) && !in_array($file->getClientMimeType(), $allowedTypes)) {
                throw new \Exception('File type not allowed');
            }

            // Validate file size (default 10MB)
            if ($file->getSize() > 10 * 1024 * 1024) {
                throw new \Exception('File size too large. Maximum allowed: 10MB');
            }

            // Generate unique filename
            $filename = $this->generateUniqueFilename($file);
            
            // Create directory if it doesn't exist
            $fullDirectory = "public/{$directory}";
            if (!Storage::exists($fullDirectory)) {
                Storage::makeDirectory($fullDirectory);
            }

            // Store file
            $path = $file->storeAs($fullDirectory, $filename);
            
            // Get public URL
            $publicUrl = Storage::url($path);

            return [
                'success' => true,
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'url' => $publicUrl,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'directory' => $directory,
            ];

        } catch (\Exception $e) {
            Log::error('File upload failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload profile picture
     */
    public function uploadProfilePicture(UploadedFile $file, int $userId): array
    {
        $allowedTypes = [
    'image/jpeg', 'image/png', 'image/jpg', 
    'image/gif', 'image/webp', 'image/bmp',
    'image/svg+xml'
];
        $directory = "profile_pictures/{$userId}";
        
        return $this->uploadFile($file, $directory, $allowedTypes);
    }

    /**
     * Upload document (ID, passport, etc.)
     */
    public function uploadDocument(UploadedFile $file, int $userId, string $documentType): array
    {
        $allowedTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        $directory = "documents/{$userId}/{$documentType}";
        
        return $this->uploadFile($file, $directory, $allowedTypes);
    }

    /**
     * Upload store/store branch images
     */
    public function uploadStoreImage(UploadedFile $file, int $storeId, string $imageType = 'general'): array
    {
        $allowedTypes = [
    'image/jpeg', 'image/png', 'image/jpg', 
    'image/gif', 'image/webp', 'image/bmp',
    'image/svg+xml'
];
        $directory = "stores/{$storeId}/{$imageType}";
        
        return $this->uploadFile($file, $directory, $allowedTypes);
    }

    /**
     * Upload offer images
     */
    public function uploadOfferImage(UploadedFile $file, int $offerId): array
    {
        $allowedTypes = [
    'image/jpeg', 'image/png', 'image/jpg', 
    'image/gif', 'image/webp', 'image/bmp',
    'image/svg+xml'
];
        $directory = "offers/{$offerId}";
        
        return $this->uploadFile($file, $directory, $allowedTypes);
    }

    /**
     * Delete a file
     */
    public function deleteFile(string $filePath): bool
    {
        try {
            if (Storage::exists($filePath)) {
                Storage::delete($filePath);
                return true;
            }
            return false;
        } catch (\Exception $e) {
            Log::error('File deletion failed', [
                'error' => $e->getMessage(),
                'file_path' => $filePath,
            ]);
            return false;
        }
    }

    /**
     * Delete multiple files
     */
    public function deleteMultipleFiles(array $filePaths): array
    {
        $results = [];
        
        foreach ($filePaths as $filePath) {
            $results[$filePath] = $this->deleteFile($filePath);
        }
        
        return $results;
    }

    /**
     * Get file information
     */
    public function getFileInfo(string $filePath): ?array
    {
        try {
            if (!Storage::exists($filePath)) {
                return null;
            }

            $metadata = Storage::getMetadata($filePath);
            
            return [
                'path' => $filePath,
                'url' => Storage::url($filePath),
                'size' => $metadata['size'] ?? 0,
                'last_modified' => $metadata['last_modified'] ?? null,
                'mime_type' => $metadata['mime_type'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get file info', [
                'error' => $e->getMessage(),
                'file_path' => $filePath,
            ]);
            return null;
        }
    }

    /**
     * Generate unique filename
     */
    private function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        
        // Clean base name (remove special characters)
        $cleanBaseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
        
        // Generate unique identifier
        $uniqueId = Str::random(16);
        
        // Add timestamp for additional uniqueness
        $timestamp = time();
        
        return "{$cleanBaseName}_{$timestamp}_{$uniqueId}.{$extension}";
    }

    /**
     * Validate image dimensions
     */
    public function validateImageDimensions(UploadedFile $file, int $minWidth = 100, int $minHeight = 100, int $maxWidth = 4000, int $maxHeight = 4000): bool
    {
        try {
            $imageInfo = getimagesize($file->getPathname());
            
            if (!$imageInfo) {
                return false;
            }
            
            [$width, $height] = $imageInfo;
            
            return $width >= $minWidth && $width <= $maxWidth && 
                   $height >= $minHeight && $height <= $maxHeight;
                   
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Resize and optimize image
     */
    public function optimizeImage(string $filePath, int $maxWidth = 1200, int $maxHeight = 1200, int $quality = 85): bool
    {
        try {
            // This would integrate with image processing libraries like Intervention Image
            // For now, returning true as placeholder
            Log::info("Image optimization requested for: {$filePath}");
            return true;
        } catch (\Exception $e) {
            Log::error('Image optimization failed', [
                'error' => $e->getMessage(),
                'file_path' => $filePath,
            ]);
            return false;
        }
    }

    /**
     * Get storage statistics
     */
    public function getStorageStats(): array
    {
        try {
            $totalSize = 0;
            $fileCount = 0;
            
            // Calculate total size and file count for all directories
            $directories = ['profile_pictures', 'documents', 'stores', 'offers'];
            
            foreach ($directories as $directory) {
                $files = Storage::allFiles("public/{$directory}");
                $fileCount += count($files);
                
                foreach ($files as $file) {
                    $totalSize += Storage::size($file);
                }
            }
            
            return [
                'total_files' => $fileCount,
                'total_size_bytes' => $totalSize,
                'total_size_mb' => round($totalSize / (1024 * 1024), 2),
                'total_size_gb' => round($totalSize / (1024 * 1024 * 1024), 2),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get storage stats', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
