<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class StoreImageController extends Controller
{
    /**
     * Upload multiple store images
     */
    public function uploadBatch(Request $request, $storeId): JsonResponse
    {
        try {
            $request->validate([
                'image_urls' => 'required|array|min:1|max:10',
                'image_urls.*' => ['required','url','regex:/\.(jpg|jpeg|png|webp)(\?.*)?$/i'],
                'main_image_url' => ['nullable','url','regex:/\.(jpg|jpeg|png|webp)(\?.*)?$/i'],
            ]);

            $store = Store::findOrFail($storeId);
            
            // Check if user owns this store
            if ($store->user_id !== Auth::id()) {
                return response()->json([
                    'message' => 'Unauthorized. You can only manage your own store images.',
                ], 403);
            }

            $imageUrls = $request->input('image_urls');
            $mainImageUrl = $request->input('main_image_url');

            // Validate that main image is in the list
            if ($mainImageUrl && !in_array($mainImageUrl, $imageUrls)) {
                return response()->json([
                    'message' => 'Main image URL must be one of the uploaded image URLs.',
                ], 422);
            }

            // Get existing gallery images
            $existingGallery = $store->gallery_images ?? [];
            
            // Add new images to gallery
            foreach ($imageUrls as $imageUrl) {
                $existingGallery[] = [
                    'url' => $imageUrl,
                    'type' => 'image',
                    'created_at' => now()->toISOString(),
                ];
            }

            // Update store with new gallery and main image
            $store->gallery_images = $existingGallery;
            if ($mainImageUrl) {
                $store->main_image_url = $mainImageUrl;
            }
            $store->save();

            Log::info('Store images uploaded successfully', [
                'store_id' => $storeId,
                'image_count' => count($imageUrls),
                'main_image' => $mainImageUrl,
            ]);

            return response()->json([
                'message' => 'Images uploaded successfully',
                'data' => [
                    'store_id' => $store->id,
                    'gallery_images' => $store->gallery_images,
                    'main_image_url' => $store->main_image_url,
                    'total_images' => count($store->gallery_images ?? []),
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error uploading store images', [
                'store_id' => $storeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to upload images',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update store main image
     */
    public function updateMainImage(Request $request, $storeId): JsonResponse
    {
        try {
            $request->validate([
                'main_image_url' => ['required','url','regex:/\.(jpg|jpeg|png|webp)(\?.*)?$/i'],
            ]);

            $store = Store::findOrFail($storeId);
            
            // Check if user owns this store
            if ($store->user_id !== Auth::id()) {
                return response()->json([
                    'message' => 'Unauthorized. You can only manage your own store images.',
                ], 403);
            }

            $mainImageUrl = $request->input('main_image_url');
            $galleryImages = $store->gallery_images ?? [];

            // Check if main image is in the gallery
            $imageExists = false;
            foreach ($galleryImages as $image) {
                if ($image['url'] === $mainImageUrl) {
                    $imageExists = true;
                    break;
                }
            }

            if (!$imageExists) {
                return response()->json([
                    'message' => 'Main image URL must be one of the existing gallery images.',
                ], 422);
            }

            $store->main_image_url = $mainImageUrl;
            $store->save();

            Log::info('Store main image updated successfully', [
                'store_id' => $storeId,
                'main_image_url' => $mainImageUrl,
            ]);

            return response()->json([
                'message' => 'Main image updated successfully',
                'data' => [
                    'store_id' => $store->id,
                    'main_image_url' => $store->main_image_url,
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating store main image', [
                'store_id' => $storeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to update main image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get store images
     */
    public function getImages($storeId): JsonResponse
    {
        try {
            $store = Store::findOrFail($storeId);
            
            return response()->json([
                'data' => [
                    'store_id' => $store->id,
                    'gallery_images' => $store->gallery_images ?? [],
                    'main_image_url' => $store->main_image_url,
                    'total_images' => count($store->gallery_images ?? []),
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error getting store images', [
                'store_id' => $storeId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to get store images',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete store image
     */
    public function deleteImage(Request $request, $storeId): JsonResponse
    {
        try {
            $request->validate([
                'image_url' => ['required','url','regex:/\.(jpg|jpeg|png|webp)(\?.*)?$/i'],
            ]);

            $store = Store::findOrFail($storeId);
            
            // Check if user owns this store
            if ($store->user_id !== Auth::id()) {
                return response()->json([
                    'message' => 'Unauthorized. You can only manage your own store images.',
                ], 403);
            }

            $imageUrl = $request->input('image_url');
            $galleryImages = $store->gallery_images ?? [];

            // Remove image from gallery
            $galleryImages = array_filter($galleryImages, function($image) use ($imageUrl) {
                return $image['url'] !== $imageUrl;
            });

            // Reset array keys
            $galleryImages = array_values($galleryImages);

            // If deleted image was main image, set first remaining image as main
            if ($store->main_image_url === $imageUrl) {
                $store->main_image_url = !empty($galleryImages) ? $galleryImages[0]['url'] : null;
            }

            $store->gallery_images = $galleryImages;
            $store->save();

            Log::info('Store image deleted successfully', [
                'store_id' => $storeId,
                'deleted_image_url' => $imageUrl,
            ]);

            return response()->json([
                'message' => 'Image deleted successfully',
                'data' => [
                    'store_id' => $store->id,
                    'gallery_images' => $store->gallery_images,
                    'main_image_url' => $store->main_image_url,
                    'total_images' => count($store->gallery_images ?? []),
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error deleting store image', [
                'store_id' => $storeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to delete image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}