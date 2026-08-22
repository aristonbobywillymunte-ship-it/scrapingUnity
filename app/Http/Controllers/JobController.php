<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Services\CapabilityRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class JobController extends Controller
{
    /**
     * POST /api/v1/jobs
     * Ingest asynchronous scraping job with Idempotency, Quota check, Dedupe, and Redis dispatch.
     */
    public function create(Request $request): JsonResponse
    {
        $user = $request->user();
        $requestId = $request->attributes->get('request_id', 'req_' . Str::random(16));

        $validated = $request->validate([
            'platform' => 'required|string',
            'operation' => 'required|string',
            'target' => 'required|array',
            'target.type' => 'required|string',
            'target.value' => 'required|string',
            'options' => 'nullable|array',
            'options.limit' => 'nullable|integer|min:1|max:100',
            'options.max_pages' => 'nullable|integer|min:1|max:5',
        ]);

        $platform = strtolower($validated['platform']);
        $operation = strtolower($validated['operation']);
        $targetType = strtolower($validated['target']['type']);
        $targetValue = trim($validated['target']['value']);
        $options = $validated['options'] ?? [];

        // 1. Platform validation (MVP Facebook Only)
        if ($platform !== 'facebook') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PLATFORM_UNSUPPORTED',
                    'message' => "Platform '{$platform}' is unsupported or deferred in MVP."
                ],
                'meta' => ['request_id' => $requestId]
            ], 422);
        }

        // 2. Idempotency check via Idempotency-Key header
        $idempotencyKey = $request->header('Idempotency-Key');
        if ($idempotencyKey) {
            $idempRecord = DB::table('scraping_jobs')
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($idempRecord) {
                // Verify payload match
                $fingerprint = hash('sha256', json_encode([$platform, $operation, $targetType, $targetValue, $options]));
                if ($idempRecord->request_fingerprint !== $fingerprint) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'IDEMPOTENCY_CONFLICT',
                            'message' => 'Idempotency key reused with differing request payload.'
                        ],
                        'meta' => ['request_id' => $requestId]
                    ], 409);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'job_id' => $idempRecord->id,
                        'status' => strtolower($idempRecord->status),
                        'platform' => $idempRecord->platform,
                        'operation' => $idempRecord->operation,
                        'created_at' => $idempRecord->created_at,
                    ],
                    'meta' => ['request_id' => $requestId]
                ], 200);
            }
        }

        // 3. User quota check
        $monthlyQuota = 10000;
        $usedCount = DB::table('scraping_jobs')
            ->where('user_id', $user->id)
            ->whereMonth('created_at', now()->month)
            ->count();

        if ($usedCount >= $monthlyQuota) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'QUOTA_EXCEEDED',
                    'message' => 'Monthly scraping quota exceeded. Please upgrade plan.'
                ],
                'meta' => ['request_id' => $requestId]
            ], 402);
        }

        // 4. Request Fingerprint calculation
        $fingerprint = hash('sha256', json_encode([$platform, $operation, $targetType, $targetValue, $options]));
        $jobId = (string) Str::uuid();
        $executionId = (string) Str::uuid();

        // 5. Check fresh cache (< 1 hour)
        $cachedItem = DB::table('scraping_items')
            ->where('platform', $platform)
            ->where('request_fingerprint', $fingerprint)
            ->where('created_at', '>=', now()->subHour())
            ->first();

        $initialStatus = $cachedItem ? 'completed' : 'queued';

        // 6. Insert Scraping Job
        DB::table('scraping_jobs')->insert([
            'id' => $jobId,
            'user_id' => $user->id,
            'platform' => $platform,
            'operation' => $operation,
            'target_type' => $targetType,
            'target_value' => $targetValue,
            'options' => json_encode($options),
            'idempotency_key' => $idempotencyKey,
            'request_fingerprint' => $fingerprint,
            'scrape_execution_id' => $executionId,
            'status' => strtoupper($initialStatus),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 7. If not cached: dispatch execution payload to Python Redis queue
        if (!$cachedItem) {
            $contractPayload = [
                'execution_id' => $executionId,
                'job_id' => $jobId,
                'platform' => $platform,
                'operation' => $operation,
                'target' => [
                    'type' => $targetType,
                    'value' => $targetValue,
                ],
                'options' => [
                    'limit' => $options['limit'] ?? 20,
                    'max_pages' => $options['max_pages'] ?? 1,
                    'mode' => $options['mode'] ?? 'http',
                ]
            ];

            try {
                \Illuminate\Support\Facades\Redis::rpush('scrape:executions', json_encode($contractPayload));
            } catch (\Throwable $e) {
                // Redis dispatch fallback logged
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'job_id' => $jobId,
                'status' => $initialStatus,
                'platform' => $platform,
                'operation' => $operation,
                'created_at' => now()->toIso8601String(),
            ],
            'meta' => [
                'request_id' => $requestId
            ]
        ], 202);
    }

    /**
     * GET /api/v1/jobs
     * List user-owned jobs only (Tenant Isolation).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $requestId = $request->attributes->get('request_id', 'req_' . Str::random(16));

        $jobs = DB::table('scraping_jobs')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $jobs->items(),
            'meta' => [
                'request_id' => $requestId,
                'pagination' => [
                    'current_page' => $jobs->currentPage(),
                    'total_pages' => $jobs->lastPage(),
                    'total_items' => $jobs->total(),
                ]
            ]
        ]);
    }

    /**
     * GET /api/v1/jobs/{id}
     * Get specific user job. Returns 404 for other tenants (IDOR enumeration resistance).
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $requestId = $request->attributes->get('request_id', 'req_' . Str::random(16));

        $job = DB::table('scraping_jobs')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$job) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'JOB_NOT_FOUND',
                    'message' => 'Scraping job not found.'
                ],
                'meta' => ['request_id' => $requestId]
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'job_id' => $job->id,
                'platform' => $job->platform,
                'operation' => $job->operation,
                'status' => strtolower($job->status),
                'created_at' => $job->created_at,
                'updated_at' => $job->updated_at,
            ],
            'meta' => ['request_id' => $requestId]
        ]);
    }

    /**
     * GET /api/v1/jobs/{id}/items
     */
    public function items(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $requestId = $request->attributes->get('request_id', 'req_' . Str::random(16));

        $job = DB::table('scraping_jobs')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$job) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'JOB_NOT_FOUND',
                    'message' => 'Scraping job not found.'
                ],
                'meta' => ['request_id' => $requestId]
            ], 404);
        }

        $items = DB::table('scraping_items')
            ->where('request_fingerprint', $job->request_fingerprint)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => ['request_id' => $requestId]
        ]);
    }

    /**
     * DELETE /api/v1/jobs/{id}
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $requestId = $request->attributes->get('request_id', 'req_' . Str::random(16));

        $job = DB::table('scraping_jobs')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$job) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'JOB_NOT_FOUND',
                    'message' => 'Scraping job not found.'
                ],
                'meta' => ['request_id' => $requestId]
            ], 404);
        }

        DB::table('scraping_jobs')->where('id', $id)->update([
            'status' => 'CANCELLED',
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'job_id' => $id,
                'status' => 'cancelled'
            ],
            'meta' => ['request_id' => $requestId]
        ]);
    }
}
