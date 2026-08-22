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
use Laravel\Sanctum\Sanctum;

class UniversalJobsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 'owner', 'description' => 'Owner', 'is_internal_role' => false],
            ['id' => 'member', 'description' => 'Member', 'is_internal_role' => false],
        ]);

        $this->user = User::create([
            'id' => Str::uuid(),
            'email' => 'api_tester@example.com',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'ACTIVE'
        ]);

        $this->org = Organization::create([
            'id' => Str::uuid(),
            'name' => 'API Org',
            'status' => 'ACTIVE'
        ]);

        OrganizationMembership::create([
            'id' => Str::uuid(),
            'user_id' => $this->user->id,
            'organization_id' => $this->org->id,
            'role_id' => 'owner',
            'role_is_internal' => false
        ]);

        DB::table('credit_lots')->insert([
            'id' => Str::uuid(),
            'organization_id' => $this->org->id,
            'original_quantity' => 1000.0,
            'remaining_quantity' => 1000.0,
            'source' => 'TOP_UP',
            'expires_at' => now()->addYear()
        ]);

        Sanctum::actingAs($this->user);
    }

    /** Test universal POST /api/v1/jobs with keyword search discovery */
    public function test_post_jobs_universal_search_query()
    {
        $response = $this->withHeader('X-Organization-Id', $this->org->id)
            ->postJson('/api/v1/jobs', [
                'platform' => 'facebook',
                'operation' => 'posts',
                'target' => [
                    'type' => 'keyword',
                    'value' => 'politik indonesia'
                ],
                'options' => [
                    'limit' => 25
                ]
            ]);

        $response->assertStatus(202)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'queued',
                    'platform' => 'facebook',
                    'operation' => 'posts'
                ]
            ]);

        $this->assertNotNull($response->json('data.job_id'));
    }

    /** Test universal POST /api/v1/jobs with hashtag search discovery */
    public function test_post_jobs_universal_hashtag()
    {
        $response = $this->withHeader('X-Organization-Id', $this->org->id)
            ->postJson('/api/v1/jobs', [
                'platform' => 'instagram',
                'operation' => 'reels',
                'target' => [
                    'type' => 'hashtag',
                    'value' => '#trending'
                ],
                'options' => [
                    'limit' => 10
                ]
            ]);

        $response->assertStatus(202)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'queued',
                    'platform' => 'instagram',
                    'operation' => 'reels'
                ]
            ]);
    }

    /** Test GET /api/v1/jobs list */
    public function test_get_jobs_list()
    {
        $postRes = $this->withHeader('X-Organization-Id', $this->org->id)
            ->postJson('/api/v1/jobs', [
                'platform' => 'facebook',
                'operation' => 'posts',
                'target' => [
                    'type' => 'keyword',
                    'value' => 'test search'
                ]
            ]);

        $jobId = $postRes->json('data.job_id');

        $listRes = $this->withHeader('X-Organization-Id', $this->org->id)
            ->getJson('/api/v1/jobs');

        $listRes->assertStatus(200)
            ->assertJson(['success' => true]);

        $showRes = $this->withHeader('X-Organization-Id', $this->org->id)
            ->getJson("/api/v1/jobs/{$jobId}");

        $showRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'job_id' => $jobId,
                    'status' => 'queued'
                ]
            ]);
    }
}
