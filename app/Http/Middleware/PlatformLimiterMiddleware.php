<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlatformLimiterMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $platform = $request->input('platform', 'facebook');
        $requestId = $request->attributes->get('request_id', 'req_' . Str::random(16));

        $limitRpm = 60; // fallback

        if ($user) {
            $userKey = "ratelimit:user:{$user->id}:" . date('YmdHi');
            
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
        
        $user = DB::table('users')->where('id', $userId)->first();
        if ($user && $user->plan_id) {
            $plan = DB::table('plans')->where('id', $user->plan_id)->first();
            if ($plan) {
                return (int) $plan->rate_limit_rpm;
            }
        }
        
        return $defaultLimit;
    }
}
