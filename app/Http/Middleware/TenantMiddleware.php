<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     * Enforces single-tenant isolation between accounts.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user) {
            // Verify tenant boundary: user can only access resources belonging to their organization
            $orgId = $user->organization_id ?? null;
            if ($request->route('organizationId') && $request->route('organizationId') !== $orgId) {
                // If user is not internal admin, reject cross-tenant route
                $isInternalAdmin = \Illuminate\Support\Facades\DB::table('internal_user_assignments')
                    ->where('user_id', $user->id)
                    ->whereIn('role_id', ['admin', 'internal_admin'])
                    ->where('is_active', true)
                    ->exists();

                if (!$isInternalAdmin) {
                    abort(403, 'Cross-tenant access forbidden.');
                }
            }
        }

        return $next($request);
    }
}
