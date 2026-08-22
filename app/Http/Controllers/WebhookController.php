<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    /**
     * GET /api/v1/webhooks
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $requestId = $request->attributes->get('request_id', 'req_' . Str::random(16));

        $webhooks = DB::table('webhooks')
            ->where('user_id', $user->id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $webhooks,
            'meta' => ['request_id' => $requestId]
        ]);
    }

    /**
     * POST /api/v1/webhooks
     */
    public function create(Request $request): JsonResponse
    {
        $user = $request->user();
        $requestId = $request->attributes->get('request_id', 'req_' . Str::random(16));

        $validated = $request->validate([
            'url' => 'required|url',
            'events' => 'required|array',
            'events.*' => 'in:job.completed,job.partial,job.failed',
        ]);

        // Webhook SSRF check: reject private / localhost URLs
        $host = parse_url($validated['url'], PHP_URL_HOST);
        if (in_array(strtolower($host), ['localhost', '127.0.0.1', '169.254.169.254']) || preg_match('/^10\.|^192\.168\.|^172\.(1[6-9]|2[0-9]|3[0-1])\./', $host)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SSRF_REJECTED',
                    'message' => 'Webhook URL cannot point to internal, private, or loopback network addresses.'
                ],
                'meta' => ['request_id' => $requestId]
            ], 422);
        }

        $secret = 'whsec_' . Str::random(32);
        $webhookId = (string) Str::uuid();

        DB::table('webhooks')->insert([
            'id' => $webhookId,
            'user_id' => $user->id,
            'url' => $validated['url'],
            'events' => json_encode($validated['events']),
            'secret' => $secret,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $webhookId,
                'url' => $validated['url'],
                'events' => $validated['events'],
                'secret' => $secret,
                'status' => 'ACTIVE',
                'created_at' => now()->toIso8601String(),
            ],
            'meta' => ['request_id' => $requestId]
        ], 201);
    }

    /**
     * GET /api/v1/webhooks/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $requestId = $request->attributes->get('request_id', 'req_' . Str::random(16));

        $webhook = DB::table('webhooks')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$webhook) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'WEBHOOK_NOT_FOUND',
                    'message' => 'Webhook subscription not found.'
                ],
                'meta' => ['request_id' => $requestId]
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $webhook,
            'meta' => ['request_id' => $requestId]
        ]);
    }

    /**
     * DELETE /api/v1/webhooks/{id}
     */
    public function delete(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $requestId = $request->attributes->get('request_id', 'req_' . Str::random(16));

        $deleted = DB::table('webhooks')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'WEBHOOK_NOT_FOUND',
                    'message' => 'Webhook subscription not found.'
                ],
                'meta' => ['request_id' => $requestId]
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => ['message' => 'Webhook deleted successfully.'],
            'meta' => ['request_id' => $requestId]
        ]);
    }
}
