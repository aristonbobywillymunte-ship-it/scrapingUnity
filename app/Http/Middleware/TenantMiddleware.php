<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use App\Models\OrganizationMembership;
use App\Services\AuditSecurityService;

class TenantMiddleware {
    public function handle(Request $request, Closure $next) {
        $orgId = $request->header('X-Organization-Id');
        if (!$orgId) return response()->json(['error' => 'Missing Org'], 400);
        
        $user = $request->user();
        if (!$user || $user->status !== 'ACTIVE') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $isMember = OrganizationMembership::where('user_id', $user->id)
            ->where('organization_id', $orgId)->exists();
            
        if (!$isMember) {
            AuditSecurityService::log('AUTHORIZATION_DENIAL', $user->id, $orgId, ['reason' => 'Tenant isolation bypass attempt']);
            return response()->json(['error' => 'Forbidden'], 403);
        }
        
        return $next($request);
    }
}
