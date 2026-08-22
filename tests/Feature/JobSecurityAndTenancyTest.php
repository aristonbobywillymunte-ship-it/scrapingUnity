<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Run;
use App\Models\RunResult;
use Laravel\Sanctum\Sanctum;

class JobSecurityAndTenancyTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private Organization $orgA;
    private User $userB;
    private Organization $orgB;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 'owner', 'description' => 'Owner', 'is_internal_role' => false],
            ['id' => 'member', 'description' => 'Member', 'is_internal_role' => false],
        ]);

        // User & Org A
        $this->userA = User::create([
            'id' => Str::uuid(),
            'email' => 'user_a@example.com',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'ACTIVE'
        ]);

        $this->orgA = Organization::create([
            'id' => Str::uuid(),
            'name' => 'Org A',
            'status' => 'ACTIVE'
        ]);

        OrganizationMembership::create([
            'id' => Str::uuid(),
            'user_id' => $this->userA->id,
            'organization_id' => $this->orgA->id,
            'role_id' => 'owner',
            'role_is_internal' => false
        ]);

        DB::table('credit_lots')->insert([
            'id' => Str::uuid(),
            'organization_id' => $this->orgA->id,
            'original_quantity' => 1000.0,
            'remaining_quantity' => 1000.0,
            'source' => 'TOP_UP',
            'expires_at' => now()->addYear()
        ]);

        // User & Org B
        $this->userB = User::create([
            'id' => Str::uuid(),
            'email' => 'user_b@example.com',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'ACTIVE'
        ]);

        $this->orgB = Organization::create([
            'id' => Str::uuid(),
            'name' => 'Org B',
            'status' => 'ACTIVE'
        ]);

        OrganizationMembership::create([
            'id' => Str::uuid(),
            'user_id' => $this->userB->id,
            'organization_id' => $this->orgB->id,
            'role_id' => 'owner',
            'role_is_internal' => false
        ]);

        DB::table('credit_lots')->insert([
            'id' => Str::uuid(),
            'organization_id' => $this->orgB->id,
            'original_quantity' => 1000.0,
            'remaining_quantity' => 1000.0,
            'source' => 'TOP_UP',
            'expires_at' => now()->addYear()
        ]);
    }

    /** User A cannot spoof X-Organization-Id of Org B */
    public function test_user_a_cannot_spoof_org_b_on_create()
    {
        Sanctum::actingAs($this->userA);

        $response = $this->withHeader('X-Organization-Id', $this->orgB->id)
            ->postJson('/api/v1/jobs', [
                'platform' => 'facebook',
                'operation' => 'posts',
                'target' => [
                    'type' => 'keyword',
                    'value' => 'spoofed search'
                ]
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN'
                ]
            ]);
    }

    /** User A cannot see User B jobs in index */
    public function test_user_a_cannot_see_user_b_jobs()
    {
        // Create job for Org B
        Sanctum::actingAs($this->userB);
        $resB = $this->withHeader('X-Organization-Id', $this->orgB->id)
            ->postJson('/api/v1/jobs', [
                'platform' => 'facebook',
                'operation' => 'posts',
                'target' => [
                    'type' => 'keyword',
                    'value' => 'private B search'
                ]
            ]);
        $jobIdB = $resB->json('data.job_id');

        // User A lists jobs
        Sanctum::actingAs($this->userA);
        $resA = $this->withHeader('X-Organization-Id', $this->orgA->id)
            ->getJson('/api/v1/jobs');

        $resA->assertStatus(200);
        $ids = collect($resA->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($jobIdB));

        // User A tries to view Org B job detail
        $showRes = $this->withHeader('X-Organization-Id', $this->orgA->id)
            ->getJson("/api/v1/jobs/{$jobIdB}");

        $showRes->assertStatus(404);
    }

    /** Test exception leakage prevention */
    public function test_raw_exception_with_secret_does_not_leak_to_client()
    {
        Sanctum::actingAs($this->userA);

        // Submit malformed target payload that causes validation/service exception
        $response = $this->withHeader('X-Organization-Id', $this->orgA->id)
            ->postJson('/api/v1/jobs', [
                'platform' => 'unsupported_platform_xyz',
                'operation' => 'posts',
                'target' => [
                    'type' => 'keyword',
                    'value' => 'secret_api_key_123456789'
                ]
            ]);

        $response->assertStatus(422);
        $content = $response->getContent();

        // Ensure no raw internal stack trace or secret is exposed
        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString('Stack trace:', $content);
    }
}
