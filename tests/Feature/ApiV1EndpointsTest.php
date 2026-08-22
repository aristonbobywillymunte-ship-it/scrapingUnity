<?php

use App\Models\User;
use App\Models\ApiKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user = User::create([
        'id' => (string) Str::uuid(),
        'email' => 'api_tenant@example.com',
        'password_hash' => Hash::make('password123'),
        'status' => 'ACTIVE'
    ]);

    $this->orgId = (string) Str::uuid();
    DB::table('organizations')->insert([
        'id' => $this->orgId,
        'name' => 'Test Organization',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->rawKey = 'sk_' . Str::random(40);
    $this->apiKey = ApiKey::create([
        'id' => (string) Str::uuid(),
        'organization_id' => $this->orgId,
        'created_by' => $this->user->id,
        'name' => 'Test API Key',
        'key_hash' => hash('sha256', $this->rawKey),
        'key_prefix' => substr($this->rawKey, 0, 8),
        'scopes' => json_encode(['jobs:read', 'jobs:write']),
        'status' => 'ACTIVE'
    ]);
});

test('Universal Jobs API requires Bearer API Key authentication', function () {
    $this->postJson('/api/v1/jobs', [
        'platform' => 'facebook',
        'operation' => 'profile',
        'target' => ['type' => 'username', 'value' => 'zuck']
    ])->assertStatus(401)
      ->assertJsonPath('error.code', 'UNAUTHORIZED');
});

test('Universal Jobs API accepts valid Bearer API Key and creates job with HTTP 202', function () {
    $res = $this->withHeader('Authorization', 'Bearer ' . $this->rawKey)
        ->postJson('/api/v1/jobs', [
            'platform' => 'facebook',
            'operation' => 'profile',
            'target' => ['type' => 'username', 'value' => 'zuck'],
            'options' => ['limit' => 5]
        ]);

    $res->assertStatus(202)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.platform', 'facebook');

    $this->assertDatabaseHas('scraping_jobs', [
        'user_id' => $this->user->id,
        'platform' => 'facebook',
        'operation' => 'profile'
    ]);
});

test('Universal Jobs API enforces Idempotency-Key semantics', function () {
    $idempKey = 'idemp_' . Str::random(16);
    $payload = [
        'platform' => 'facebook',
        'operation' => 'profile',
        'target' => ['type' => 'username', 'value' => 'zuck'],
        'options' => ['limit' => 5]
    ];

    // First request
    $res1 = $this->withHeader('Authorization', 'Bearer ' . $this->rawKey)
        ->withHeader('Idempotency-Key', $idempKey)
        ->postJson('/api/v1/jobs', $payload);
    $res1->assertStatus(202);
    $jobId = $res1->json('data.job_id');

    // Duplicate request with same payload returns original job
    $res2 = $this->withHeader('Authorization', 'Bearer ' . $this->rawKey)
        ->withHeader('Idempotency-Key', $idempKey)
        ->postJson('/api/v1/jobs', $payload);
    $res2->assertStatus(200)
        ->assertJsonPath('data.job_id', $jobId);

    // Mismatched payload with same Idempotency-Key returns 409 Conflict
    $res3 = $this->withHeader('Authorization', 'Bearer ' . $this->rawKey)
        ->withHeader('Idempotency-Key', $idempKey)
        ->postJson('/api/v1/jobs', array_merge($payload, ['options' => ['limit' => 10]]));
    $res3->assertStatus(409)
        ->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
});

test('Tenant isolation prevents User A from accessing User B jobs (IDOR protection)', function () {
    $userB = User::create([
        'id' => (string) Str::uuid(),
        'email' => 'user_b@example.com',
        'password_hash' => Hash::make('password123'),
        'status' => 'ACTIVE'
    ]);

    $jobId = (string) Str::uuid();
    DB::table('scraping_jobs')->insert([
        'id' => $jobId,
        'user_id' => $userB->id,
        'platform' => 'facebook',
        'operation' => 'profile',
        'target_type' => 'username',
        'target_value' => 'user_b_target',
        'options' => json_encode([]),
        'status' => 'COMPLETED',
        'created_at' => now(),
        'updated_at' => now()
    ]);

    // User A attempts to access User B job via API
    $this->withHeader('Authorization', 'Bearer ' . $this->rawKey)
        ->getJson("/api/v1/jobs/{$jobId}")
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'JOB_NOT_FOUND');
});

test('Platforms, Usage, and Webhooks API endpoints return standard canonical envelopes', function () {
    // Platforms
    $this->withHeader('Authorization', 'Bearer ' . $this->rawKey)
        ->getJson('/api/v1/platforms')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.platform', 'facebook');

    // Usage
    $this->withHeader('Authorization', 'Bearer ' . $this->rawKey)
        ->getJson('/api/v1/usage')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.monthly_quota', 10000);

    // Webhooks Create
    $whRes = $this->withHeader('Authorization', 'Bearer ' . $this->rawKey)
        ->postJson('/api/v1/webhooks', [
            'url' => 'https://webhook.site/my-test-endpoint',
            'events' => ['job.completed', 'job.failed']
        ]);
    $whRes->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'ACTIVE');
});
