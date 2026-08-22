<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\ApiKey;
use App\Services\AuditSecurityService;

class ApiKeyController extends Controller
{
    /**
     * GET /api/v1/api-keys
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $keys = ApiKey::where('created_by', $user->id)
            ->select('id', 'name', 'key_prefix', 'scopes', 'status', 'last_used_at', 'expires_at', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $keys,
            'meta' => ['request_id' => 'req_' . Str::random(16)]
        ]);
    }

    /**
     * POST /api/v1/api-keys
     * Create API key. Secret shown exactly once.
     */
    public function create(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'scopes' => 'nullable|array',
            'expires_in_days' => 'nullable|integer|min:1|max:365',
        ]);

        $rawSecret = 'sk_' . Str::random(37);
        $prefix = substr($rawSecret, 0, 8);
        $keyHash = hash('sha256', $rawSecret);
        $keyId = (string) Str::uuid();

        $expiresAt = isset($validated['expires_in_days']) ? now()->addDays($validated['expires_in_days']) : null;

        $requestedOrgId = $request->header('X-Organization-Id');
        if ($requestedOrgId && !\Illuminate\Support\Facades\Gate::allows('access-org', $requestedOrgId)) {
            \App\Services\AuditSecurityService::log('AUTHORIZATION_DENIAL', $user->id, $requestedOrgId, ['action' => 'api_key_create']);
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Forbidden organization access.'
                ]
            ], 403);
        }

        $orgId = $requestedOrgId ?: $user->organization_id;
        if (!$orgId) {
            $membership = \Illuminate\Support\Facades\DB::table('organization_memberships')->where('user_id', $user->id)->first();
            if ($membership) {
                $orgId = $membership->organization_id;
            } else {
                $org = \App\Models\Organization::first();
                if (!$org) {
                    $orgId = (string) Str::uuid();
                    \App\Models\Organization::create(['id' => $orgId, 'name' => 'Default Org', 'status' => 'ACTIVE']);
                } else {
                    $orgId = $org->id;
                }
            }
        }

        $apiKey = ApiKey::create([
            'id' => $keyId,
            'organization_id' => $orgId,
            'created_by' => $user->id,
            'name' => $validated['name'],
            'key_hash' => $keyHash,
            'key_prefix' => $prefix,
            'scopes' => json_encode($validated['scopes'] ?? ['jobs:read', 'jobs:write']),
            'status' => 'ACTIVE',
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditSecurityService::log('API_KEY_CREATED', $user->id, null, ['key_id' => $keyId, 'name' => $validated['name']]);

        return response()->json([
            'success' => true,
            'key' => $rawSecret,
            'data' => [
                'id' => $keyId,
                'name' => $apiKey->name,
                'key' => $rawSecret, // Displayed once
                'prefix' => $prefix,
                'status' => 'ACTIVE',
                'expires_at' => $expiresAt ? $expiresAt->toIso8601String() : null,
                'created_at' => now()->toIso8601String(),
            ],
            'meta' => ['request_id' => 'req_' . Str::random(16)]
        ], 201);
    }

    /**
     * DELETE /api/v1/api-keys/{id}
     */
    public function revoke(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $apiKey = ApiKey::where('id', $id)
            ->where('created_by', $user->id)
            ->first();

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'API_KEY_NOT_FOUND',
                    'message' => 'API Key not found.'
                ],
                'meta' => ['request_id' => 'req_' . Str::random(16)]
            ], 404);
        }

        $apiKey->update([
            'status' => 'REVOKED',
            'revoked_at' => now(),
        ]);

        AuditSecurityService::log('API_KEY_REVOKED', $user->id, null, ['key_id' => $id]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $id,
                'status' => 'REVOKED',
                'revoked_at' => now()->toIso8601String()
            ],
            'meta' => ['request_id' => 'req_' . Str::random(16)]
        ]);
    }
}
