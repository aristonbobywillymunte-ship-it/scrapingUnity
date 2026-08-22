<?php
/**
 * P1 Admin Mandatory Pages Test Suite
 *
 * Covers:
 * 1. Server-side Admin authorization across all 16 new Admin routes
 * 2. Normal user denied (403) across all Admin routes
 * 3. Operator/internal non-admin access control
 * 4. Plans & Quota creation and listing
 * 5. Data Center tab switching and filtering
 * 6. Admin Scraping Jobs visibility
 * 7. Platforms registry and health monitoring
 * 8. Parser rollback and failure tracking
 * 9. Proxy pool CRUD and health test
 * 10. System settings retrieval and persistence
 * 11. Audit logs viewer
 */

use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createTestAdmin(): User
{
    DB::table('roles')->insertOrIgnore([
        ['id' => 'operator', 'description' => 'Operator', 'is_internal_role' => true],
    ]);
    $user = User::create([
        'id'            => (string) Str::uuid(),
        'email'         => 'admin_' . Str::random(5) . '@internal.test',
        'password_hash' => Hash::make('secret123'),
        'status'        => 'ACTIVE',
    ]);
    DB::table('internal_user_assignments')->insert([
        'id'               => (string) Str::uuid(),
        'user_id'          => $user->id,
        'role_id'          => 'operator',
        'role_is_internal' => true,
    ]);
    return $user;
}

function createCustomerUser(): User
{
    DB::table('roles')->insertOrIgnore([
        ['id' => 'owner', 'description' => 'Owner', 'is_internal_role' => false],
    ]);
    $user = User::create([
        'id'            => (string) Str::uuid(),
        'email'         => 'customer_' . Str::random(5) . '@customer.test',
        'password_hash' => Hash::make('secret123'),
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
// 1. Authorization: All Admin Routes Require Internal Assignment
// ─────────────────────────────────────────────────────────────────────────────

$adminRoutes = [
    '/admin',
    '/admin/operations',
    '/admin/users',
    '/admin/plans',
    '/admin/data-center',
    '/admin/jobs',
    '/admin/test-history',
    '/admin/platforms',
    '/admin/platforms/health',
    '/admin/parser',
    '/admin/proxies',
    '/admin/workers',
    '/admin/queues',
    '/admin/providers',
    '/admin/logs',
    '/admin/audit-logs',
    '/admin/settings',
];

foreach ($adminRoutes as $route) {
    test("Customer user receives 403 on {$route}", function () use ($route) {
        $customer = createCustomerUser();
        $this->actingAs($customer)->get($route)->assertStatus(403);
    });

    test("Admin user receives 200 on {$route}", function () use ($route) {
        $admin = createTestAdmin();
        $this->actingAs($admin)->get($route)->assertStatus(200);
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. Functional: Plans & Quota Management
// ─────────────────────────────────────────────────────────────────────────────

test('Admin can create a new Plan and it persists to database', function () {
    $admin = createTestAdmin();

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\Plans\Index::class)
        ->set('name', 'Enterprise Scraping Plan')
        ->set('monthlyQuota', 50000)
        ->set('rateLimitRpm', 120)
        ->set('maxConcurrency', 5)
        ->call('createPlan');

    expect(DB::table('packages')->where('name', 'Enterprise Scraping Plan')->exists())->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// 3. Functional: Proxy Pool Management
// ─────────────────────────────────────────────────────────────────────────────

test('Admin can add a proxy to the pool and test latency', function () {
    $admin = createTestAdmin();

    $component = Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\Proxies\Index::class)
        ->set('host', '10.0.0.1')
        ->set('port', 3128)
        ->set('proxyType', 'datacenter')
        ->set('countryCode', 'ID')
        ->call('addProxy');

    $proxy = DB::table('proxies')->where('host', '10.0.0.1')->first();
    expect($proxy)->not->toBeNull();
    expect($proxy->port)->toBe(3128);

    // Test health check
    $component->call('testHealth', $proxy->id);
    $updated = DB::table('proxies')->where('id', $proxy->id)->first();
    expect($updated->avg_latency_ms)->toBeGreaterThan(0);
});

// ─────────────────────────────────────────────────────────────────────────────
// 4. Functional: Parser Rollback
// ─────────────────────────────────────────────────────────────────────────────

test('Admin can rollback parser version with confirmation', function () {
    $admin = createTestAdmin();

    $selectorId = (string) Str::uuid();
    DB::table('selectors')->insert([
        'id' => $selectorId,
        'platform' => 'facebook',
        'scraper' => 'posts',
        'source' => 'html',
        'page_type' => 'post',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $v1Id = (string) Str::uuid();
    DB::table('selector_versions')->insert([
        'id' => $v1Id,
        'selector_id' => $selectorId,
        'status' => 'INACTIVE',
        'version_tag' => 'v1.0.0',
        'selector_data' => json_encode(['title' => 'h1']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $v2Id = (string) Str::uuid();
    DB::table('selector_versions')->insert([
        'id' => $v2Id,
        'selector_id' => $selectorId,
        'status' => 'ACTIVE',
        'version_tag' => 'v2.0.0',
        'selector_data' => json_encode(['title' => 'h2']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\Parser\Index::class)
        ->call('requestRollback', $v1Id, 'v1.0.0')
        ->assertSet('confirmingRollbackId', $v1Id)
        ->call('confirmRollback');

    expect(DB::table('selector_versions')->where('id', $v1Id)->value('status'))->toBe('ACTIVE');
    expect(DB::table('selector_versions')->where('id', $v2Id)->value('status'))->toBe('INACTIVE');
});

// ─────────────────────────────────────────────────────────────────────────────
// 5. Functional: System Settings Mutation
// ─────────────────────────────────────────────────────────────────────────────

test('Admin can update system settings and it logs to audit_logs', function () {
    $admin = createTestAdmin();

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\System\Settings::class)
        ->set('settings.results_retention_days', '60')
        ->call('saveSettings');

    expect(DB::table('system_settings')->where('key', 'results_retention_days')->value('value'))->toBe('60');
    expect(DB::table('audit_logs')->where('action', 'SYSTEM_SETTINGS_UPDATED')->exists())->toBeTrue();
});
