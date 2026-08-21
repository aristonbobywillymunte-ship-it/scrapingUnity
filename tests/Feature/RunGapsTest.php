<?php
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Services\RunPreflightService;
use App\Services\RunResultService;

beforeEach(function () {
    
    DB::table('roles')->insertOrIgnore([
        ['id' => 'owner', 'is_internal_role' => false],
        ['id' => 'internal_admin', 'is_internal_role' => true]
    ]);
});

test('RunPreflight validation', function() {
    $u1 = User::create(['id' => Str::uuid(), 'email' => 'preflight@a.com', 'password_hash' => '123', 'status' => 'ACTIVE']);
    $o1 = Organization::create(['id' => Str::uuid(), 'name' => 'O1', 'status' => 'ACTIVE']);
    OrganizationMembership::create(['id' => Str::uuid(), 'user_id' => $u1->id, 'organization_id' => $o1->id, 'role_id' => 'owner']);
    
    $service = new RunPreflightService();
    
    // Valid
    $this->assertTrue($service->validate($u1, $o1->id, 'facebook_posts'));
    
    // Unsupported capability
    $this->expectExceptionMessage('Unsupported capability');
    $service->validate($u1, $o1->id, 'INVALID_CAPABILITY');
});

test('RunPreflight rejects inactive actor', function() {
    $u1 = User::create(['id' => Str::uuid(), 'email' => 'preflight2@a.com', 'password_hash' => '123', 'status' => 'SUSPENDED']);
    $o1 = Organization::create(['id' => Str::uuid(), 'name' => 'O12', 'status' => 'ACTIVE']);
    OrganizationMembership::create(['id' => Str::uuid(), 'user_id' => $u1->id, 'organization_id' => $o1->id, 'role_id' => 'owner']);
    
    $service = new RunPreflightService();
    $this->expectExceptionMessage('Actor inactive');
    $service->validate($u1, $o1->id, 'facebook_posts');
});
