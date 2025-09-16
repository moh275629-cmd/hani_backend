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

        $paramsToSign = [
            'folder' => $folder,
            'resource_type' => $resourceType,
            'timestamp' => $timestamp,
            'type' => $type,
        ];
        if (!empty($publicId)) {
            $paramsToSign['public_id'] = $publicId;
        }

        ksort($paramsToSign);
        $toSign = http_build_query($paramsToSign, '', '&', PHP_QUERY_RFC3986);
        $apiSecret = config('cloudinary.api_secret') ?? config('services.cloudinary.api_secret');
        if (empty($apiSecret)) {
            return response()->json(['message' => 'Cloudinary secret not configured'], 500);
        }
        $signature = hash('sha1', urldecode($toSign) . $apiSecret);

        return response()->json([
            'timestamp' => $timestamp,
            'signature' => $signature,
            'api_key' => config('cloudinary.api_key') ?? config('services.cloudinary.api_key'),
            'cloud_name' => config('cloudinary.cloud_name') ?? config('services.cloudinary.cloud_name'),
            'folder' => $folder,
            'resource_type' => $resourceType,
            'type' => $type,
        ]);
    }
}


