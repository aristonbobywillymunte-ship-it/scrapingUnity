<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\CapabilityRegistry;
use Illuminate\Support\Str;

class PlatformController extends Controller
{
    /**
     * GET /api/v1/platforms
     */
    public function index(Request $request): JsonResponse
    {
        $requestId = $request->attributes->get('request_id', 'req_' . Str::random(16));
        $capabilities = CapabilityRegistry::all();

        $platforms = [
            [
                'platform' => 'facebook',
                'name' => 'Facebook',
                'status' => 'ACTIVE',
                'description' => 'Facebook Public Content Scraping Adapter (HTTP & Browser).',
                'supported_operations' => ['profile', 'posts', 'comments'],
                'enabled' => true,
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $platforms,
            'meta' => [
                'request_id' => $requestId
            ]
        ]);
    }

    /**
     * GET /api/v1/platforms/{platform}
     */
    public function show(Request $request, string $platform): JsonResponse
    {
        $requestId = $request->attributes->get('request_id', 'req_' . Str::random(16));
        if (strtolower($platform) !== 'facebook') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PLATFORM_NOT_FOUND',
                    'message' => "Platform '{$platform}' is not supported or deferred."
                ],
                'meta' => [
                    'request_id' => $requestId
                ]
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'platform' => 'facebook',
                'name' => 'Facebook',
                'status' => 'ACTIVE',
                'description' => 'Facebook Public Content Scraping Adapter (HTTP & Browser).',
                'supported_operations' => ['profile', 'posts', 'comments'],
                'capabilities' => CapabilityRegistry::all(),
                'enabled' => true,
            ],
            'meta' => [
                'request_id' => $requestId
            ]
        ]);
    }
}
