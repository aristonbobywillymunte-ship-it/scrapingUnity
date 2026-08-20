<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\ApiKey;
use Illuminate\Support\Facades\Gate;
use App\Services\AuditSecurityService;

class ApiKeyController extends Controller {
    public function create(Request $request) {
        $orgId = $request->header('X-Organization-Id');
        if (!Gate::allows('access-org', $orgId)) {
            AuditSecurityService::log('AUTHORIZATION_DENIAL', request()->user()->id, $orgId, ['action' => 'api_key_create']);
            return response()->json(['error' => 'Forbidden'], 403);
        }
        
        $request->validate(['name' => 'required', 'scopes' => 'required|array']);
        
        $rawKey = Str::random(40);
        $keyHash = hash('sha256', $rawKey);
        
        $apiKey = ApiKey::create([
            'id' => Str::uuid(),
            'organization_id' => $orgId,
            'created_by' => request()->user()->id,
            'name' => $request->name,
            'key_hash' => $keyHash,
            'key_prefix' => substr($rawKey, 0, 8),
            'scopes' => json_encode($request->scopes),
            'status' => 'ACTIVE'
        ]);
        
        AuditSecurityService::log('API_KEY_CREATED', request()->user()->id, $orgId, ['key_id' => $apiKey->id]);
        return response()->json(['key' => $rawKey]);
    }
}
