<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class OfferImageController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    /**
     * Upload offer image/video to Cloudinary
     */
    public function uploadMedia(Request $request, $offerId): JsonResponse
    {
        try {
            $offer = Offer::findOrFail($offerId);

            $validator = Validator::make($request->all(), [
                // Accept either media file or media_url (Cloudinary URL)
                'media' => 'file|mimes:jpg,jpeg,png,mp4,mov,avi|max:51200',
                'media_url' => 'url',
                'is_main_media' => 'boolean',
                'media_type' => 'in:image,video',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $isMainMedia = $request->boolean('is_main_media', false);
            $mediaType = $request->input('media_type', 'image');

            // If media_url provided, store it directly, else upload file
            $finalUrl = null;
            if ($request->filled('media_url')) {
                $finalUrl = $request->input('media_url');
            } elseif ($request->hasFile('media')) {
                $cloudinaryResult = $this->cloudinaryService->uploadOfferMedia(
                    $request->file('media'),
                    $offerId,
                    $isMainMedia
                );

                if (!$cloudinaryResult['success']) {
                    return response()->json([
                        'message' => 'Media upload failed',
                        'error' => $cloudinaryResult['error'],
                    ], 500);
                }
                $finalUrl = $cloudinaryResult['secure_url'] ?? $cloudinaryResult['url'];
            } else {
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => ['Either media file or media_url must be provided'],
                ], 422);
            }

            // Update offer with media URL
            if ($isMainMedia) {
                $offer->setMainMedia($finalUrl);
            } else {
                $offer->addGalleryMedia($finalUrl, $mediaType);
            }

            return response()->json([
                'message' => 'Media uploaded successfully',
                'data' => [
                    'url' => $finalUrl,
                    'public_id' => null,
                    'is_main_media' => $isMainMedia,
                    'media_type' => $mediaType,
                ],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Offer not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Offer media upload error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'offer_id' => $offerId,
            ]);

            return response()->json([
                'message' => 'Media upload failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get offer media
     */
    public function getMedia($offerId): JsonResponse
    {
        try {
            $offer = Offer::findOrFail($offerId);

            $media = [
                'main_media' => $offer->getMainMediaUrl(),
                'gallery_media' => $offer->getGalleryMedia(),
                'images' => $offer->getImages(),
                'videos' => $offer->getVideos(),
            ];

            return response()->json([
                'message' => 'Offer media retrieved successfully',
                'data' => $media,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Offer not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get offer media error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to retrieve offer media',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Delete offer media
     */
    public function deleteMedia(Request $request, $offerId): JsonResponse
    {
        try {
            $offer = Offer::findOrFail($offerId);

            $validator = Validator::make($request->all(), [
                'media_url' => 'required|string',
                'is_main_media' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $mediaUrl = $request->input('media_url');
            $isMainMedia = $request->boolean('is_main_media', false);

            // Remove from offer
            if ($isMainMedia) {
                if ($offer->getMainMediaUrl() === $mediaUrl) {
                    $offer->main_media_url = null;
                    $offer->save();
                }
            } else {
                $offer->removeGalleryMedia($mediaUrl);
            }

            // TODO: Delete from Cloudinary if needed
            // For now, we'll just remove from database

            return response()->json([
                'message' => 'Media deleted successfully',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Offer not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Delete offer media error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to delete media',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
