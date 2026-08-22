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

    $this->rawKey = 'sk_' . Str::random(37);
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

test('API Key Scopes: Read-only API key is forbidden from POST /api/v1/jobs', function () {
    $readOnlyRawKey = 'sk_' . Str::random(37);
    ApiKey::create([
        'id' => (string) Str::uuid(),
        'organization_id' => $this->orgId,
        'created_by' => $this->user->id,
        'name' => 'Read Only Key',
        'key_hash' => hash('sha256', $readOnlyRawKey),
        'key_prefix' => substr($readOnlyRawKey, 0, 8),
        'scopes' => json_encode(['jobs:read']),
        'status' => 'ACTIVE'
    ]);

    $this->withHeader('Authorization', 'Bearer ' . $readOnlyRawKey)
        ->postJson('/api/v1/jobs', [
            'platform' => 'facebook',
            'operation' => 'profile',
            'target' => ['type' => 'username', 'value' => 'zuck']
        ])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'INSUFFICIENT_SCOPE');
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

    $this->assertDatabaseHas('scrape_executions', [
        'platform' => 'facebook',
        'operation' => 'profile'
    ]);
});

test('Coalescing: Two identical concurrent requests share one scrape_execution', function () {
    $user2 = User::create([
        'id' => (string) Str::uuid(),
        'email' => 'user_coalesce_2@example.com',
        'password_hash' => Hash::make('password123'),
        'status' => 'ACTIVE'
    ]);
    $key2Raw = 'sk_' . Str::random(37);
    ApiKey::create([
        'id' => (string) Str::uuid(),
        'organization_id' => $this->orgId,
        'created_by' => $user2->id,
        'name' => 'Key User 2',
        'key_hash' => hash('sha256', $key2Raw),
        'key_prefix' => substr($key2Raw, 0, 8),
        'scopes' => json_encode(['jobs:read', 'jobs:write']),
        'status' => 'ACTIVE'
    ]);

    $payload = [
        'platform' => 'facebook',
        'operation' => 'profile',
        'target' => ['type' => 'username', 'value' => 'coalesced_target'],
        'options' => ['limit' => 10]
    ];

    // Job 1 by User 1
    $res1 = $this->withHeader('Authorization', 'Bearer ' . $this->rawKey)
        ->postJson('/api/v1/jobs', $payload);
    $res1->assertStatus(202);
    $job1Id = $res1->json('data.job_id');

    // Job 2 by User 2 (same payload while execution is active/queued)
    $res2 = $this->withHeader('Authorization', 'Bearer ' . $key2Raw)
        ->postJson('/api/v1/jobs', $payload);
    $res2->assertStatus(202);
    $job2Id = $res2->json('data.job_id');

    $this->assertNotEquals($job1Id, $job2Id, 'Customer jobs must be distinct');

    $job1 = DB::table('scraping_jobs')->where('id', $job1Id)->first();
    $job2 = DB::table('scraping_jobs')->where('id', $job2Id)->first();

    $this->assertEquals($job1->scrape_execution_id, $job2->scrape_execution_id, 'Both jobs must share the exact same scrape_execution_id');
    $this->assertEquals('COALESCED', $job2->resolution);

    $execCount = DB::table('scrape_executions')->where('request_fingerprint', $job1->request_fingerprint)->count();
    $this->assertEquals(1, $execCount, 'Exactly one upstream execution must be created for coalesced jobs');
});

test('Cache: Reuses fresh completed result and records usage in ledger with zero upstream dispatch', function () {
    $payload = [
        'platform' => 'facebook',
        'operation' => 'profile',
        'target' => ['type' => 'username', 'value' => 'cached_target'],
        'options' => ['limit' => 1]
    ];

    $fingerprint = hash('sha256', json_encode(['facebook', 'profile', 'username', 'cached_target', ['limit' => 1]]));

    // Insert prior fresh cached scraping item
    DB::table('scraping_items')->insert([
        'id' => (string) Str::uuid(),
        'platform' => 'facebook',
        'content_type' => 'PROFILE',
        'external_id' => 'fb_prof_cached_1',
        'canonical_url' => 'https://facebook.com/cached_target',
        'request_fingerprint' => $fingerprint,
        'author' => json_encode(['username' => null, 'display_name' => 'Cached Target']),
        'text' => 'Cached Bio',
        'collected_at' => now(),
        'parser_version' => '1.0.0',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $res = $this->withHeader('Authorization', 'Bearer ' . $this->rawKey)
        ->postJson('/api/v1/jobs', $payload);

    $res->assertStatus(202)
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.resolution', 'cache');

    $jobId = $res->json('data.job_id');
    $this->assertDatabaseHas('usage_ledger', [
        'user_id' => $this->user->id,
        'job_id' => $jobId,
        'resolution' => 'cache',
        'records_delivered' => 1
    ]);
});

test('GET /api/v1/results and PATCH /api/v1/webhooks/{id} work canonically', function () {
    // 1. GET /api/v1/results
    $this->withHeader('Authorization', 'Bearer ' . $this->rawKey)
        ->getJson('/api/v1/results')
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    // 2. POST /api/v1/webhooks
    $whCreate = $this->withHeader('Authorization', 'Bearer ' . $this->rawKey)
        ->postJson('/api/v1/webhooks', [
            'url' => 'https://webhook.site/test-patch-wh',
            'events' => ['job.completed']
        ]);
    $whCreate->assertStatus(201);
    $whId = $whCreate->json('data.id');

    // 3. PATCH /api/v1/webhooks/{id}
    $whPatch = $this->withHeader('Authorization', 'Bearer ' . $this->rawKey)
        ->patchJson("/api/v1/webhooks/{$whId}", [
            'events' => ['job.completed', 'job.failed'],
            'status' => 'DISABLED'
        ]);

    $whPatch->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'DISABLED');
});
