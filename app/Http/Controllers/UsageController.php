<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UsageController extends Controller
{
    /**
     * GET /api/v1/usage
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $requestId = $request->attributes->get('request_id', 'req_' . Str::random(16));

        $monthlyQuota = 10000;
        $usedCount = DB::table('scraping_jobs')
            ->where('user_id', $user->id)
            ->whereMonth('created_at', now()->month)
            ->count();

        $remaining = max(0, $monthlyQuota - $usedCount);

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'plan' => 'standard',
                'monthly_quota' => $monthlyQuota,
                'used_this_month' => $usedCount,
                'remaining_quota' => $remaining,
                'rate_limit_rpm' => 60,
                'max_concurrency' => 2,
                'allowed_platforms' => ['facebook'],
            ],
            'meta' => [
                'request_id' => $requestId
            ]
        ]);
    }
}
