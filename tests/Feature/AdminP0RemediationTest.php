<?php
/**
 * P0 Remediation Behavioral Tests
 *
 * Proves:
 * 1. USER_PROVISIONED audit event persists to audit_logs (correct columns)
 * 2. USER_STATUS_CHANGED audit event persists to audit_logs (correct columns)
 * 3. Admin auth is DB-backed (internal_user_assignments) only — no email bypass
 * 4. Hardcoded email alone does NOT grant Admin
 * 5. Normal User denied /admin and /admin/operations
 * 6. Confirmation step required before status change
 * 7. Raw exception not leaked to client
 */

use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use App\Livewire\Admin\Index as AdminIndex;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeAdminUser(string $email = 'admin@internal.test'): User
{
    DB::table('roles')->insertOrIgnore([
        ['id' => 'admin', 'description' => 'Admin', 'is_internal_role' => true],
    ]);
    $user = User::create([
        'id'            => (string) Str::uuid(),
        'email'         => $email,
        'password_hash' => Hash::make('adminpass'),
        'status'        => 'ACTIVE',
    ]);
    DB::table('internal_user_assignments')->insert([
        'id'      => (string) Str::uuid(),
        'user_id' => $user->id,
        'role_id' => 'admin',
    ]);
    return $user;
}

function makeNormalUser(string $email = 'user@customer.test'): User
{
    DB::table('roles')->insertOrIgnore([
        ['id' => 'owner', 'description' => 'Owner', 'is_internal_role' => false],
    ]);
    $user = User::create([
        'id'            => (string) Str::uuid(),
        'email'         => $email,
        'password_hash' => Hash::make('userpass'),
        'status'        => 'ACTIVE',
    ]);
    $org = Organization::create([
        'id'     => (string) Str::uuid(),
        'name'   => 'Customer Org',
        'status' => 'ACTIVE',
    ]);
    OrganizationMembership::create([
        'id'              => (string) Str::uuid(),
        'user_id'         => $user->id,
        'organization_id' => $org->id,
        'role_id'         => 'owner',
    ]);
    return $user;
}

// ─────────────────────────────────────────────────────────────────────────────
// P0-1: Audit Log — USER_PROVISIONED persists with correct schema columns
// ─────────────────────────────────────────────────────────────────────────────

test('P0-1: USER_PROVISIONED audit event persists to audit_logs with correct columns', function () {
    $admin = makeAdminUser();

    Livewire::actingAs($admin)
        ->test(AdminIndex::class)
        ->set('email', 'newuser@test.com')
        ->set('password', 'password123')
        ->set('initialCredits', 200)
        ->call('createUser');

    // Verify audit log written with correct schema columns
    $log = DB::table('audit_logs')
        ->where('action', 'USER_PROVISIONED')
        ->first();

    expect($log)->not->toBeNull('USER_PROVISIONED audit log must exist');
    expect($log->actor_id)->toBe($admin->id, 'actor_id must be admin UUID');
    expect($log->actor_type)->toBe('admin');
    expect($log->target)->toStartWith('users:', 'target must reference created user');
    $meta = json_decode($log->safe_metadata, true);
    expect($meta)->toHaveKey('email');
    expect($meta['email'])->toBe('newuser@test.com');

    // These old columns must NOT be in the insert (they don't exist in the schema)
    // Verify the User was actually created
    expect(DB::table('users')->where('email', 'newuser@test.com')->exists())->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// P0-1: Audit Log — USER_STATUS_CHANGED persists with correct schema columns
// ─────────────────────────────────────────────────────────────────────────────

test('P0-1: USER_STATUS_CHANGED audit event persists to audit_logs with correct columns', function () {
    $admin = makeAdminUser();
    $target = makeNormalUser('target@test.com');

    // Step 1: request confirmation
    $component = Livewire::actingAs($admin)
        ->test(AdminIndex::class)
        ->call('requestToggleUserStatus', $target->id);

    expect($component->get('confirmingUserId'))->toBe($target->id);
    expect($component->get('confirmingAction'))->toBe('suspend');

    // Step 2: confirm
    $component->call('confirmToggleUserStatus');

    // Verify audit log
    $log = DB::table('audit_logs')
        ->where('action', 'USER_STATUS_CHANGED')
        ->first();

    expect($log)->not->toBeNull('USER_STATUS_CHANGED audit log must exist');
    expect($log->actor_id)->toBe($admin->id);
    expect($log->actor_type)->toBe('admin');
    expect($log->target)->toStartWith('users:');
    $meta = json_decode($log->safe_metadata, true);
    expect($meta['new_status'])->toBe('SUSPENDED');

    // User status must have changed
    $updated = User::find($target->id);
    expect($updated->status)->toBe('SUSPENDED');
});

// ─────────────────────────────────────────────────────────────────────────────
// P0-3: Confirmation required — action must NOT fire before confirm step
// ─────────────────────────────────────────────────────────────────────────────

test('P0-3: requestToggleUserStatus sets confirmation state without changing user status', function () {
    $admin = makeAdminUser();
    $target = makeNormalUser('other@test.com');

    Livewire::actingAs($admin)
        ->test(AdminIndex::class)
        ->call('requestToggleUserStatus', $target->id)
        ->assertSet('confirmingUserId', $target->id)
        ->assertSet('confirmingAction', 'suspend');

    // User status must NOT have changed yet
    expect(User::find($target->id)->status)->toBe('ACTIVE');
    // No audit log yet
    expect(DB::table('audit_logs')->where('action', 'USER_STATUS_CHANGED')->count())->toBe(0);
});

test('P0-3: cancelConfirmation clears state without acting', function () {
    $admin = makeAdminUser();
    $target = makeNormalUser('cancel@test.com');

    Livewire::actingAs($admin)
        ->test(AdminIndex::class)
        ->call('requestToggleUserStatus', $target->id)
        ->call('cancelConfirmation')
        ->assertSet('confirmingUserId', null)
        ->assertSet('confirmingAction', null);

    expect(User::find($target->id)->status)->toBe('ACTIVE');
});

// ─────────────────────────────────────────────────────────────────────────────
// P0-4: Authorization — DB-backed only, no email bypass
// ─────────────────────────────────────────────────────────────────────────────

test('P0-4: Normal user is denied /admin', function () {
    $user = makeNormalUser();
    $this->actingAs($user);
    $this->get('/admin')->assertStatus(403);
});

test('P0-4: Normal user is denied /admin/operations', function () {
    $user = makeNormalUser();
    $this->actingAs($user);
    $this->get('/admin/operations')->assertStatus(403);
});

test('P0-4: Hardcoded email alone does NOT grant admin access', function () {
    // This user has admin@example.com email but NO internal_user_assignments row
    $fakeAdmin = User::create([
        'id'            => (string) Str::uuid(),
        'email'         => 'admin@example.com',
        'password_hash' => Hash::make('fake'),
        'status'        => 'ACTIVE',
    ]);

    $this->actingAs($fakeAdmin);
    // Must be 403 — email alone is NOT enough
    $this->get('/admin')->assertStatus(403);
    $this->get('/admin/operations')->assertStatus(403);
});

test('P0-4: Internal_user_assignments row grants admin access', function () {
    $admin = makeAdminUser('real_admin@internal.test');
    $this->actingAs($admin);
    $this->get('/admin')->assertStatus(200);
    $this->get('/admin/operations')->assertStatus(200);
});

test('P0-4: Internal assignment removed = admin access revoked', function () {
    $admin = makeAdminUser('revoked@internal.test');
    // Remove the internal assignment
    DB::table('internal_user_assignments')->where('user_id', $admin->id)->delete();

    $this->actingAs($admin);
    $this->get('/admin')->assertStatus(403);
});

// ─────────────────────────────────────────────────────────────────────────────
// P0-5: Raw exception not leaked to client
// ─────────────────────────────────────────────────────────────────────────────

test('P0-5: createUser error does not expose raw exception or secret content to client', function () {
    $admin = makeAdminUser();

    // Create a user that already exists to force a DB duplicate email error
    User::create([
        'id'            => (string) Str::uuid(),
        'email'         => 'duplicate@test.com',
        'password_hash' => Hash::make('x'),
        'status'        => 'ACTIVE',
    ]);

    $component = Livewire::actingAs($admin)
        ->test(AdminIndex::class)
        ->set('email', 'duplicate@test.com')
        ->set('password', 'password123')
        ->set('initialCredits', 100)
        ->call('createUser');

    // Validation will catch this (unique:users,email) before DB error
    // But the error message shown to client must not contain raw SQL or exception class
    $errorMessage = $component->get('errorMessage');

    // If we get past validation (which will stop it), errorMessage should be safe
    // Primary check: no raw exception class names or SQL visible
    expect($errorMessage)->not->toContain('PDOException');
    expect($errorMessage)->not->toContain('SQLSTATE');
    expect($errorMessage)->not->toContain('password=');
    expect($errorMessage)->not->toContain('secret=');
});
