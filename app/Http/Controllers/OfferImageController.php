<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class OfferImageController extends Controller
{
    /**
     * Upload multiple offer images
     */
    public function uploadBatch(Request $request, $offerId): JsonResponse
    {
        try {
            $request->validate([
                'image_urls' => 'required|array|min:1|max:10',
                'image_urls.*' => 'required|url',
                'main_image_url' => 'nullable|url',
            ]);

            $offer = Offer::findOrFail($offerId);
            
            // Check if user owns this offer's store
            if ($offer->store->user_id !== Auth::id()) {
                return response()->json([
                    'message' => 'Unauthorized. You can only manage images for your own offers.',
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

            // Add new images to gallery using the existing method
            foreach ($imageUrls as $imageUrl) {
                $offer->addGalleryMedia($imageUrl, 'image');
            }

            // Set main image if provided
            if ($mainImageUrl) {
                $offer->setMainMedia($mainImageUrl);
            }

            Log::info('Offer images uploaded successfully', [
                'offer_id' => $offerId,
                'image_count' => count($imageUrls),
                'main_image' => $mainImageUrl,
            ]);

            return response()->json([
                'message' => 'Images uploaded successfully',
                'data' => [
                    'offer_id' => $offer->id,
                    'gallery_media' => $offer->getGalleryMedia(),
                    'main_media_url' => $offer->getMainMediaUrl(),
                    'total_images' => count($offer->getImages()),
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error uploading offer images', [
                'offer_id' => $offerId,
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
     * Update offer main image
     */
    public function updateMainImage(Request $request, $offerId): JsonResponse
    {
        try {
            $request->validate([
                'main_image_url' => 'required|url',
            ]);

            $offer = Offer::findOrFail($offerId);
            
            // Check if user owns this offer's store
            if ($offer->store->user_id !== Auth::id()) {
                return response()->json([
                    'message' => 'Unauthorized. You can only manage images for your own offers.',
                ], 403);
            }

            $mainImageUrl = $request->input('main_image_url');
            $galleryImages = $offer->getImages();

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

            $offer->setMainMedia($mainImageUrl);

            Log::info('Offer main image updated successfully', [
                'offer_id' => $offerId,
                'main_image_url' => $mainImageUrl,
            ]);

            return response()->json([
                'message' => 'Main image updated successfully',
                'data' => [
                    'offer_id' => $offer->id,
                    'main_media_url' => $offer->getMainMediaUrl(),
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating offer main image', [
                'offer_id' => $offerId,
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
     * Get offer images
     */
    public function getImages($offerId): JsonResponse
    {
        try {
            $offer = Offer::findOrFail($offerId);
            
            return response()->json([
                'data' => [
                    'offer_id' => $offer->id,
                    'gallery_media' => $offer->getGalleryMedia(),
                    'main_media_url' => $offer->getMainMediaUrl(),
                    'images' => $offer->getImages(),
                    'videos' => $offer->getVideos(),
                    'total_images' => count($offer->getImages()),
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error getting offer images', [
                'offer_id' => $offerId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to get offer images',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete offer image
     */
    public function deleteImage(Request $request, $offerId): JsonResponse
    {
        try {
            $request->validate([
                'image_url' => 'required|url',
            ]);

            $offer = Offer::findOrFail($offerId);
            
            // Check if user owns this offer's store
            if ($offer->store->user_id !== Auth::id()) {
                return response()->json([
                    'message' => 'Unauthorized. You can only manage images for your own offers.',
                ], 403);
            }

            $imageUrl = $request->input('image_url');

            // Remove image from gallery using existing method
            $offer->removeGalleryMedia($imageUrl);

            // If deleted image was main image, set first remaining image as main
            if ($offer->getMainMediaUrl() === $imageUrl) {
                $remainingImages = $offer->getImages();
                $offer->setMainMedia(!empty($remainingImages) ? $remainingImages[0]['url'] : null);
            }

            Log::info('Offer image deleted successfully', [
                'offer_id' => $offerId,
                'deleted_image_url' => $imageUrl,
            ]);

            return response()->json([
                'message' => 'Image deleted successfully',
                'data' => [
                    'offer_id' => $offer->id,
                    'gallery_media' => $offer->getGalleryMedia(),
                    'main_media_url' => $offer->getMainMediaUrl(),
                    'total_images' => count($offer->getImages()),
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error deleting offer image', [
                'offer_id' => $offerId,
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