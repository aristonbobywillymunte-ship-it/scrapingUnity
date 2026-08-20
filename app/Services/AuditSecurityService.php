<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditSecurityService {
    public static function log(string $action, ?string $actorId = null, ?string $orgId = null, array $metadata = []) {
        unset($metadata['password'], $metadata['otp'], $metadata['api_key'], $metadata['auth_token'], $metadata['authorization']);
        DB::table('security_events')->insert([
            'id' => Str::uuid(),
            'event_type' => $action,
            'actor_id' => $actorId,
            'organization_id' => $orgId,
            'safe_context' => json_encode($metadata),
            'created_at' => now()
        ]);
    }
}
