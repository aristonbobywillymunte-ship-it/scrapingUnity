<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlatformLimiterMiddleware
{
    /**
     * Rate limiter per User resolved from Canonical Package limits.
     * Prevents fail-open in production if Redis is down.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $requestId = $request->attributes->get('request_id', 'req_' . Str::random(16));

        if ($user) {
            $userKey = "ratelimit:user:{$user->id}:" . date('YmdHi');
            
            // Resolve factual limit from User -> Organization -> Subscription -> Package -> Entitlements
            $limitRpm = $this->resolveUserRateLimit($user->id);

            try {
                $current = (int) Redis::get($userKey);
                if ($current >= $limitRpm) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'API_RATE_LIMITED',
                            'message' => 'API rate limit exceeded.'
                        ],
                        'meta' => [
                            'request_id' => $requestId,
                            'retry_after' => 60 - date('s')
                        ]
                    ], 429)->header('Retry-After', 60 - date('s'))
                        ->header('X-RateLimit-Limit', $limitRpm)
                        ->header('X-RateLimit-Remaining', 0)
                        ->header('X-RateLimit-Reset', time() + (60 - date('s')));
                }
                Redis::incr($userKey);
                Redis::expire($userKey, 120);
                
                $response = $next($request);
                $response->headers->set('X-RateLimit-Limit', $limitRpm);
                $response->headers->set('X-RateLimit-Remaining', max(0, $limitRpm - $current - 1));
                $response->headers->set('X-RateLimit-Reset', time() + (60 - date('s')));
                
                return $response;

            } catch (\Throwable $e) {
                if (!app()->environment('testing') && !app()->runningUnitTests()) {
                    // Do not fail open in production if Redis is unreachable
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'SERVICE_UNAVAILABLE',
                            'message' => 'Rate limiter service is currently unavailable.'
                        ],
                        'meta' => ['request_id' => $requestId]
                    ], 503);
                }
            }
        }

        return $next($request);
    }

    private function resolveUserRateLimit(string $userId): int
    {
        $defaultLimit = 60;
        
        $membership = DB::table('organization_memberships')
            ->where('user_id', $userId)
            ->first();
            
        if (!$membership) return $defaultLimit;

        $subscription = DB::table('subscriptions')
            ->where('organization_id', $membership->organization_id)
            ->where('status', 'ACTIVE')
            ->where('starts_at', '<=', now())
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$subscription) return $defaultLimit;

        $entitlement = DB::table('package_entitlements')
            ->where('package_id', $subscription->package_id)
            ->where('capability', 'api_access')
            ->first();

        if ($entitlement && $entitlement->limits) {
            $limits = is_string($entitlement->limits) ? json_decode($entitlement->limits, true) : $entitlement->limits;
            if (isset($limits['rate_limit_rpm'])) {
                return (int) $limits['rate_limit_rpm'];
            }
        }

        return $defaultLimit;
    }
}
