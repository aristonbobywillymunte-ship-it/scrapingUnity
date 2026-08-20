<?php
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationMembership;

beforeEach(function () {
    Artisan::call('migrate:raw-down');
    Artisan::call('migrate:raw');
    DB::table('roles')->insertOrIgnore([
        ['id' => 'owner', 'is_internal_role' => false],
        ['id' => 'internal_admin', 'is_internal_role' => true]
    ]);
});

test('Run API HTTP endpoints', function() {
    $u1 = User::create(['id' => Str::uuid(), 'email' => 'runapi@a.com', 'password_hash' => \Illuminate\Support\Facades\Hash::make('123'), 'status' => 'ACTIVE']);
    $o1 = Organization::create(['id' => Str::uuid(), 'name' => 'O1', 'status' => 'ACTIVE']);
    $o2 = Organization::create(['id' => Str::uuid(), 'name' => 'O2', 'status' => 'ACTIVE']);
    
    OrganizationMembership::create(['id' => Str::uuid(), 'user_id' => $u1->id, 'organization_id' => $o1->id, 'role_id' => 'owner']);
    
    $this->postJson('/api/v1/auth/login', ['email' => 'runapi@a.com', 'password' => '123']);
    
    // Create Run
    $resCreate = $this->withSession(session()->all())->postJson('/api/v1/facebook/posts/runs', ['target_url' => 'test'], ['X-Organization-Id' => $o1->id]);
    $resCreate->assertStatus(201);
    $runId = $resCreate->json('id');
    $this->assertNotNull($runId);
    
    // Get Run
    $resGet = $this->withSession(session()->all())->getJson("/api/v1/runs/{$runId}", ['X-Organization-Id' => $o1->id]);
    $resGet->assertStatus(200);
    $this->assertEquals('QUEUED', $resGet->json('status'));
    
    // Cross-org Get Run Denied (Not Found usually for IDOR)
    $resGetDenied = $this->withSession(session()->all())->getJson("/api/v1/runs/{$runId}", ['X-Organization-Id' => $o2->id]);
    $resGetDenied->assertStatus(403); // Middleware blocks it before getRun
    
    // Cancel Run
    $resCancel = $this->withSession(session()->all())->postJson("/api/v1/runs/{$runId}/cancel", [], ['X-Organization-Id' => $o1->id]);
    $resCancel->assertStatus(200);
    $this->assertEquals('CANCELLED', $resCancel->json('status'));
    
    // List Runs
    $resList = $this->withSession(session()->all())->getJson('/api/v1/runs', ['X-Organization-Id' => $o1->id]);
    $resList->assertStatus(200);
    $this->assertCount(1, $resList->json());
});
