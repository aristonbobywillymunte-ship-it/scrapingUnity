<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use App\Models\ApiKey;
class ApiKeyMiddleware {
    public function handle(Request $request, Closure $next) {
        $token = $request->bearerToken();
        if (!$token) return response()->json(['error' => 'API Key required'], 401);
        $hash = hash('sha256', $token);
        $key = ApiKey::where('key_hash', $hash)->first();
        if (!$key || $key->status !== 'ACTIVE' || $key->revoked_at) {
            return response()->json(['error' => 'Invalid or revoked API Key'], 401);
        }
        $key->update(['last_used_at' => now()]);
        $request->attributes->set('api_key', $key);
        return $next($request);
    }
}
