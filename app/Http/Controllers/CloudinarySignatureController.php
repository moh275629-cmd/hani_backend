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

        // Only sign actual upload parameters.
        // Do NOT include cloud_name, api_key, file, or resource_type in the signature string.
        $paramsToSign = [
            'folder' => $folder,
            'timestamp' => (int) $timestamp,
            'type' => $type,
        ];
        if (!empty($publicId)) {
            $paramsToSign['public_id'] = $publicId;
        }

        // Sort by key and build key=value pairs without URL-encoding (per Cloudinary spec)
        ksort($paramsToSign);
        $pairs = [];
        foreach ($paramsToSign as $key => $value) {
            $pairs[] = $key . '=' . $value;
        }
        $stringToSign = implode('&', $pairs);

        $apiSecret = config('cloudinary.api_secret') ?? config('services.cloudinary.api_secret');
        if (empty($apiSecret)) {
            return response()->json(['message' => 'Cloudinary secret not configured'], 500);
        }
        $signature = sha1($stringToSign . $apiSecret);

        return response()->json([
            'timestamp' => (int) $timestamp,
            'signature' => $signature,
            'api_key' => config('cloudinary.api_key') ?? config('services.cloudinary.api_key'),
            'cloud_name' => config('cloudinary.cloud_name') ?? config('services.cloudinary.cloud_name'),
            'folder' => $folder,
            'type' => $type,
            // Client should choose correct upload endpoint for resource_type (image/video/raw)
        ]);
    }
}


