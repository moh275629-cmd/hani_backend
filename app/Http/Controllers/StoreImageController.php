<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class StoreImageController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    /**
     * Upload store image to Cloudinary
     */
    public function uploadImage(Request $request, $storeId): JsonResponse
    {
        try {
            $store = Store::findOrFail($storeId);

            $validator = Validator::make($request->all(), [
                // Accept either image file or image_url (Cloudinary URL)
                'image' => 'file|mimes:jpg,jpeg,png|max:10240',
                'image_url' => 'url',
                'is_main_image' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $isMainImage = $request->boolean('is_main_image', false);

            // If image_url provided, store it directly, else upload file
            $finalUrl = null;
            if ($request->filled('image_url')) {
                $finalUrl = $request->input('image_url');
            } elseif ($request->hasFile('image')) {
                $cloudinaryResult = $this->cloudinaryService->uploadStoreImage(
                    $request->file('image'),
                    $storeId,
                    $isMainImage
                );

                if (!$cloudinaryResult['success']) {
                    return response()->json([
                        'message' => 'Image upload failed',
                        'error' => $cloudinaryResult['error'],
                    ], 500);
                }
                $finalUrl = $cloudinaryResult['secure_url'] ?? $cloudinaryResult['url'];
            } else {
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => ['Either image file or image_url must be provided'],
                ], 422);
            }

            // Update store with image URL
            if ($isMainImage) {
                $store->setMainImage($finalUrl);
            } else {
                $store->addGalleryImage($finalUrl);
            }

            return response()->json([
                'message' => 'Image uploaded successfully',
                'data' => [
                    'url' => $finalUrl,
                    'public_id' => null,
                    'is_main_image' => $isMainImage,
                ],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Store not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Store image upload error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'store_id' => $storeId,
            ]);

            return response()->json([
                'message' => 'Image upload failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
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

            $images = [
                'main_image' => $store->getMainImageUrl(),
                'gallery_images' => $store->getGalleryImages(),
            ];

            return response()->json([
                'message' => 'Store images retrieved successfully',
                'data' => $images,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Store not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get store images error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to retrieve store images',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Delete store image
     */
    public function deleteImage(Request $request, $storeId): JsonResponse
    {
        try {
            $store = Store::findOrFail($storeId);

            $validator = Validator::make($request->all(), [
                'image_url' => 'required|string',
                'is_main_image' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $imageUrl = $request->input('image_url');
            $isMainImage = $request->boolean('is_main_image', false);

            // Remove from store
            if ($isMainImage) {
                if ($store->getMainImageUrl() === $imageUrl) {
                    $store->main_image_url = null;
                    $store->save();
                }
            } else {
                $store->removeGalleryImage($imageUrl);
            }

            // TODO: Delete from Cloudinary if needed
            // For now, we'll just remove from database

            return response()->json([
                'message' => 'Image deleted successfully',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Store not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Delete store image error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to delete image',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
