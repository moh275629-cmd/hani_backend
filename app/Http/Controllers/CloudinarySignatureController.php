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
        $folder = $request->input('folder', 'hani/documents');
        $publicId = $request->input('public_id');
        $resourceType = $request->input('resource_type', 'auto');
        $type = $request->input('type', 'upload');

        // Build parameters to sign - ONLY include what Cloudinary expects
        $paramsToSign = [
            'folder' => $folder,
            'timestamp' => (int) $timestamp,
        ];

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

        // Per Cloudinary: signature = SHA1(string_to_sign + api_secret)
        $signature = sha1($stringToSign . $apiSecret);

        // Debug (do not log secrets)
        Log::debug('Cloudinary Signature Debug', [
            'string_to_sign' => $stringToSign,
            'generated_signature' => $signature,
            'params_to_sign' => $paramsToSign,
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