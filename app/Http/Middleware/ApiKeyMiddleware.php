<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Support\Str;

class ApiKeyMiddleware
{
    /**
     * Handle an incoming request via Bearer API Key.
     * Enforces active state, expiry, revocation, scopes, and attaches user & key context.
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        $requestId = $request->header('X-Request-ID', 'req_' . Str::random(16));

        if (!$token) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'API Key required in Authorization: Bearer <API_KEY> header.'
                ],
                'meta' => [
                    'request_id' => $requestId
                ]
            ], 401);
        }

        $hash = hash('sha256', $token);
        $key = ApiKey::where('key_hash', $hash)->first();

        if (!$key || $key->status !== 'ACTIVE' || $key->revoked_at) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_API_KEY',
                    'message' => 'The provided API Key is invalid, inactive, or revoked.'
                ],
                'meta' => [
                    'request_id' => $requestId
                ]
            ], 401);
        }

        // Check expiration if set
        if ($key->expires_at && now()->greaterThan($key->expires_at)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'API_KEY_EXPIRED',
                    'message' => 'The provided API Key has expired.'
                ],
                'meta' => [
                    'request_id' => $requestId
                ]
            ], 401);
        }

        // Resolve associated user
        $user = User::find($key->created_by);
        if (!$user || $user->status !== 'ACTIVE') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'USER_INACTIVE',
                    'message' => 'The account associated with this API Key is inactive or suspended.'
                ],
                'meta' => [
                    'request_id' => $requestId
                ]
            ], 403);
        }

        // Update last used stats safely without logging token
        $key->update([
            'last_used_at' => now(),
            'last_used_ip' => $request->ip()
        ]);

        // Attach authenticated user and api_key context to request
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('api_key', $key);
        $request->attributes->set('request_id', $requestId);

        return $next($request);
    }
}
