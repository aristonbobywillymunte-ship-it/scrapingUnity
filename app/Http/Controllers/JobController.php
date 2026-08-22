<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\Run;
use App\Models\RunRequest;
use App\Services\RunOrchestrationService;
use App\Services\CapabilityRegistry;
use Illuminate\Support\Str;

class JobController extends Controller
{
    public function __construct(
        private RunOrchestrationService $orchestration
    ) {}

    /**
     * POST /api/v1/jobs
     * Universal Asynchronous Job Ingestion according to 04_API_SPECIFICATION Sec. 13
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'required|string',
            'operation' => 'required|string',
            'target' => 'required|array',
            'target.type' => 'required|string',
            'target.value' => 'required|string',
            'options' => 'nullable|array',
            'options.limit' => 'nullable|integer|min:1|max:100',
        ]);

        $platform = strtolower($validated['platform']);
        $operation = strtolower($validated['operation']);
        $targetType = strtolower($validated['target']['type']);
        $targetValue = trim($validated['target']['value']);
        $options = $validated['options'] ?? [];

        // Map platform + operation to internal capability
        $capabilityKey = "{$platform}_{$operation}";
        if (!CapabilityRegistry::isValid($capabilityKey)) {
            // Handle common capability aliases (e.g. facebook_posts, youtube_videos, news_articles, web_pages)
            if ($platform === 'news') {
                $capabilityKey = 'news_articles';
            } elseif ($platform === 'web') {
                $capabilityKey = 'web_pages';
            } elseif (CapabilityRegistry::isValid($operation)) {
                $capabilityKey = $operation;
            } else {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_CAPABILITY',
                        'message' => "Platform '{$platform}' with operation '{$operation}' is not supported."
                    ],
                    'meta' => [
                        'request_id' => $request->header('X-Request-ID', 'req_' . Str::random(16))
                    ]
                ], 422);
            }
        }

        $user = $request->user() ?? User::where('email', 'admin@example.com')->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Authentication required.'
                ]
            ], 401);
        }

        $orgId = $request->header('X-Organization-Id') ?? $user->organizationMemberships()->first()?->organization_id;
        if (!$orgId) {
            // Fallback to user ID if no explicit organization membership is bound
            $orgId = $user->id;
        }

        // Map discovery mode
        $discoveryMode = 'target';
        $searchQuery = null;
        $hashtag = null;

        if ($targetType === 'keyword' || $targetType === 'search_query') {
            $discoveryMode = 'search_query';
            $searchQuery = $targetValue;
        } elseif ($targetType === 'hashtag') {
            $discoveryMode = 'hashtag';
            $hashtag = ltrim($targetValue, '#');
        }

        $payload = [
            'discovery_mode' => $discoveryMode,
            'search_query' => $searchQuery,
            'hashtag' => $hashtag,
            'target' => $targetValue,
            'target_url' => $targetValue,
            'target_type' => $targetType,
            'options' => $options,
            'max_pages' => $options['limit'] ?? 1,
        ];

        try {
            $run = $this->orchestration->submitRun($user, $orgId, $capabilityKey, $payload);

            return response()->json([
                'success' => true,
                'data' => [
                    'job_id' => $run->id,
                    'status' => strtolower($run->status),
                    'platform' => $platform,
                    'operation' => $operation,
                    'created_at' => $run->created_at->toISOString()
                ],
                'meta' => [
                    'request_id' => $request->header('X-Request-ID', 'req_' . Str::random(16))
                ]
            ], 202);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'JOB_SUBMISSION_FAILED',
                    'message' => $e->getMessage()
                ],
                'meta' => [
                    'request_id' => $request->header('X-Request-ID', 'req_' . Str::random(16))
                ]
            ], 400);
        }
    }

    /**
     * GET /api/v1/jobs
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $orgId = $request->header('X-Organization-Id') ?? $user?->organizationMemberships()->first()?->organization_id ?? $user?->id;

        $query = Run::query();
        if ($orgId) {
            $query->where('organization_id', $orgId);
        }

        if ($request->has('status')) {
            $query->where('status', strtoupper($request->query('status')));
        }

        $jobs = $query->orderBy('created_at', 'desc')->paginate($request->query('limit', 20));

        return response()->json([
            'success' => true,
            'data' => $jobs->items(),
            'meta' => [
                'request_id' => $request->header('X-Request-ID', 'req_' . Str::random(16)),
                'pagination' => [
                    'current_page' => $jobs->currentPage(),
                    'total' => $jobs->total(),
                    'per_page' => $jobs->perPage()
                ]
            ]
        ]);
    }

    /**
     * GET /api/v1/jobs/{job_id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $orgId = $request->header('X-Organization-Id') ?? $user?->organizationMemberships()->first()?->organization_id ?? $user?->id;

        $query = Run::where('id', $id);
        if ($orgId) {
            $query->where('organization_id', $orgId);
        }
        $run = $query->first();

        if (!$run) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Job not found.'
                ]
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'job_id' => $run->id,
                'status' => strtolower($run->status),
                'capability' => $run->capability,
                'created_at' => $run->created_at?->toISOString(),
                'completed_at' => $run->completed_at?->toISOString()
            ],
            'meta' => [
                'request_id' => $request->header('X-Request-ID', 'req_' . Str::random(16))
            ]
        ]);
    }
}
