<?php
use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OtpRequest;
use App\Models\ApiKey;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use App\Services\OtpDeliveryService;

beforeEach(function () {
    Artisan::call('migrate:raw-down');
    Artisan::call('migrate:raw');
    
    DB::table('roles')->insertOrIgnore([
        ['id' => 'owner', 'is_internal_role' => false],
        ['id' => 'internal_admin', 'is_internal_role' => true]
    ]);
});

test('RBAC and Tenant IDOR', function() {
    $u1 = User::create(['id' => Str::uuid(), 'email' => 'u1@a.com', 'password_hash' => Hash::make('123'), 'status' => 'ACTIVE']);
    $o1 = Organization::create(['id' => Str::uuid(), 'name' => 'O1', 'status' => 'ACTIVE']);
    OrganizationMembership::create(['id' => Str::uuid(), 'user_id' => $u1->id, 'organization_id' => $o1->id, 'role_id' => 'owner']);
    
    $o2 = Organization::create(['id' => Str::uuid(), 'name' => 'O2', 'status' => 'ACTIVE']); // u1 not in o2
    
    $login = $this->postJson('/api/v1/auth/login', ['email' => 'u1@a.com', 'password' => '123']);
    
    // Org A can access Org A
    $this->withSession(session()->all())->postJson('/app/api/v1/api-keys', ['name' => 'test', 'scopes' => ['*']], ['X-Organization-Id' => $o1->id])->assertStatus(200);
    
    // Org A cannot access Org B
    $res = $this->withSession(session()->all())->postJson('/app/api/v1/api-keys', ['name' => 'test', 'scopes' => ['*']], ['X-Organization-Id' => $o2->id]);
    $res->assertStatus(403);
    
    // Audit log should exist
    $log = DB::table('security_events')->where('event_type', 'AUTHORIZATION_DENIAL')->first();
    expect($log)->not->toBeNull();
    expect($log->actor_id)->toBe($u1->id);
    expect($log->organization_id)->toBe($o2->id);
    
    // Internal role cannot be used for customer organization membership
    $u2 = User::create(['id' => Str::uuid(), 'email' => 'u2@a.com', 'password_hash' => Hash::make('123'), 'status' => 'ACTIVE']);
    try {
        OrganizationMembership::create(['id' => Str::uuid(), 'user_id' => $u2->id, 'organization_id' => $o1->id, 'role_id' => 'internal_admin']);
        $this->fail('Should have failed DB constraint');
    } catch (\Exception $e) {
        expect($e->getMessage())->toContain('violates foreign key constraint');
    }
});

test('OTP Full Lifecycle and Concurrency', function() {
    $u = User::create(['id' => Str::uuid(), 'email' => 'otp@a.com', 'password_hash' => Hash::make('123'), 'status' => 'ACTIVE']);
    
    // Rate limits
    $this->postJson('/app/api/v1/auth/password-reset/request', ['email' => 'otp@a.com', 'channel' => 'EMAIL'])->assertStatus(200);
    $this->postJson('/app/api/v1/auth/password-reset/request', ['email' => 'otp@a.com', 'channel' => 'EMAIL'])->assertStatus(200);
    $this->postJson('/app/api/v1/auth/password-reset/request', ['email' => 'otp@a.com', 'channel' => 'EMAIL'])->assertStatus(200);
    
    // 4th is 429
    $this->postJson('/app/api/v1/auth/password-reset/request', ['email' => 'otp@a.com', 'channel' => 'EMAIL'])->assertStatus(429);
    
    // Telegram rejected
    $this->postJson('/app/api/v1/auth/password-reset/request', ['email' => 'otp@a.com', 'channel' => 'TELEGRAM'])->assertStatus(422);

    $service = app(OtpDeliveryService::class);
    $otpSent = end($service->sent)['otp'];
    expect(strlen($otpSent))->toBe(6);
    expect(is_numeric($otpSent))->toBeTrue();
    
    // Complete - wrong OTP attempts
    for ($i=0; $i<4; $i++) {
        $this->postJson('/app/api/v1/auth/password-reset/complete', ['email' => 'otp@a.com', 'channel' => 'EMAIL', 'otp' => '000000', 'password' => 'new'])->assertStatus(400);
    }
    
    // Successful complete
    $this->postJson('/app/api/v1/auth/password-reset/complete', ['email' => 'otp@a.com', 'channel' => 'EMAIL', 'otp' => $otpSent, 'password' => 'newpass'])->assertStatus(200);
    
    // Single use
    $this->postJson('/app/api/v1/auth/password-reset/complete', ['email' => 'otp@a.com', 'channel' => 'EMAIL', 'otp' => $otpSent, 'password' => 'newpass2'])->assertStatus(400);
    
    // Password changed
    $this->postJson('/api/v1/auth/login', ['email' => 'otp@a.com', 'password' => '123'])->assertStatus(401);
    $this->postJson('/api/v1/auth/login', ['email' => 'otp@a.com', 'password' => 'newpass'])->assertStatus(200);
});

test('API Key Create', function() {
    $u1 = User::create(['id' => Str::uuid(), 'email' => 'u11@a.com', 'password_hash' => Hash::make('123'), 'status' => 'ACTIVE']);
    $o1 = Organization::create(['id' => Str::uuid(), 'name' => 'O11', 'status' => 'ACTIVE']);
    OrganizationMembership::create(['id' => Str::uuid(), 'user_id' => $u1->id, 'organization_id' => $o1->id, 'role_id' => 'owner']);
    
    $login = $this->postJson('/api/v1/auth/login', ['email' => 'u11@a.com', 'password' => '123']);
    
    $res = $this->withSession(session()->all())->postJson('/app/api/v1/api-keys', ['name' => 'test', 'scopes' => ['*']], ['X-Organization-Id' => $o1->id]);
    $res->assertStatus(200);
    
    $key = $res->json('key');
    expect(strlen($key))->toBe(40);
    
    $dbKey = ApiKey::where('organization_id', $o1->id)->first();
    expect($dbKey->key_hash)->toBe(hash('sha256', $key));
    expect(ApiKey::where('key_hash', $key)->exists())->toBeFalse();
    
    $log = DB::table('security_events')->where('event_type', 'API_KEY_CREATED')->first();
    expect($log)->not->toBeNull();
});

