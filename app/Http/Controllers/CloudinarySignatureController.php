<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class CloudinarySignatureController extends Controller
{
    public function sign(Request $request): JsonResponse
    {
        $timestamp = $request->input('timestamp') ?: time();
        $folder = $request->input('folder', 'hani');
        $publicId = $request->input('public_id');
        $resourceType = $request->input('resource_type', 'auto');
        $type = $request->input('type', 'upload');

        // Build parameters to sign - Cloudinary expects specific format
        $paramsToSign = [
            'folder' => $folder,
            'timestamp' => (int) $timestamp,
        ];
        
        // Add optional parameters if provided
        if (!empty($publicId)) {
            $paramsToSign['public_id'] = $publicId;
        }
        if (!empty($type) && $type !== 'upload') {
            $paramsToSign['type'] = $type;
        }
        if (!empty($resourceType) && $resourceType !== 'image') {
            $paramsToSign['resource_type'] = $resourceType;
        }

        // Sort by key and build key=value pairs
        ksort($paramsToSign);
        $pairs = [];
        foreach ($paramsToSign as $key => $value) {
            $pairs[] = $key . '=' . $value;
        }
        $stringToSign = implode('&', $pairs);

        $apiSecret = config('cloudinary.api_secret') ?? config('services.cloudinary.api_secret');
        if (empty($apiSecret)) {
            Log::error('Cloudinary API secret not configured');
            return response()->json(['message' => 'Cloudinary secret not configured'], 500);
        }

        // Use hash_hmac for more secure signing (Cloudinary supports both sha1 and sha256)
        $signature = hash_hmac('sha1', $stringToSign, $apiSecret);
        // Alternatively, you can use sha256 for better security:
        // $signature = hash_hmac('sha256', $stringToSign, $apiSecret);

        Log::debug('Cloudinary signature generated', [
            'string_to_sign' => $stringToSign,
            'signature' => $signature,
            'timestamp' => $timestamp
        ]);

        return response()->json([
            'timestamp' => (int) $timestamp,
            'signature' => $signature,
            'api_key' => config('cloudinary.api_key') ?? config('services.cloudinary.api_key'),
            'cloud_name' => config('cloudinary.cloud_name') ?? config('services.cloudinary.cloud_name'),
            'folder' => $folder,
            'type' => $type,
            'resource_type' => $resourceType,
        ]);
    }
}