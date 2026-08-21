<?php
use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Run;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Cross Tenant Run Authorization', function () {
    DB::table('roles')->insertOrIgnore([['id' => 'owner', 'description' => 'Owner', 'is_internal_role' => false]]);
    
    $userA = User::create(['id' => Str::uuid(), 'email' => 'a@example.com', 'password_hash' => Hash::make('123'), 'status' => 'ACTIVE']);
    $orgA = Organization::create(['id' => Str::uuid(), 'name' => 'Org A', 'status' => 'ACTIVE']);
    OrganizationMembership::create(['id' => Str::uuid(), 'user_id' => $userA->id, 'organization_id' => $orgA->id, 'role_id' => 'owner']);

    $userB = User::create(['id' => Str::uuid(), 'email' => 'b@example.com', 'password_hash' => Hash::make('123'), 'status' => 'ACTIVE']);
    $orgB = Organization::create(['id' => Str::uuid(), 'name' => 'Org B', 'status' => 'ACTIVE']);
    OrganizationMembership::create(['id' => Str::uuid(), 'user_id' => $userB->id, 'organization_id' => $orgB->id, 'role_id' => 'owner']);

    $runB = Run::create([
        'id' => Str::uuid(), 'organization_id' => $orgB->id, 'capability' => 'facebook_posts', 'status' => 'COMPLETED', 'origin' => 'API'
    ]);

    $this->actingAs($userA);
    $this->withSession(['organization_id' => $orgA->id]);
    
    // Attempt to view Org B's run
    $response = $this->get('/runs/' . $runB->id);
    $response->assertStatus(403); // Cross-tenant IDOR check in controller
});

test('Customer cannot access admin', function () {
    DB::table('roles')->insertOrIgnore([['id' => 'owner', 'description' => 'Owner', 'is_internal_role' => false]]);
    
    $user = User::create(['id' => Str::uuid(), 'email' => 'c@example.com', 'password_hash' => Hash::make('123'), 'status' => 'ACTIVE']);
    $org = Organization::create(['id' => Str::uuid(), 'name' => 'Org', 'status' => 'ACTIVE']);
    OrganizationMembership::create(['id' => Str::uuid(), 'id' => Str::uuid(), 'user_id' => $user->id, 'organization_id' => $org->id, 'role_id' => 'owner']);

    $this->actingAs($user);
    $this->get('/admin')->assertStatus(403);
    $this->get('/admin/operations')->assertStatus(403);
});

test('Internal user can access admin', function () {
    DB::table('roles')->insertOrIgnore([['id' => 'admin', 'description' => 'Admin', 'is_internal_role' => true]]);
    $user = User::create(['id' => Str::uuid(), 'email' => 'admin@example.com', 'password_hash' => Hash::make('123'), 'status' => 'ACTIVE']);
    
    DB::table('internal_user_assignments')->insert([
        'id' => Str::uuid(), 'user_id' => $user->id,
        'role_id' => 'admin',
    ]);

    $this->actingAs($user);
    $this->get('/admin')->assertStatus(200);
    $this->get('/admin/operations')->assertStatus(200);
});
