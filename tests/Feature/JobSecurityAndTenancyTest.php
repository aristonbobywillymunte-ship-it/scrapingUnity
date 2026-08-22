<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Organization;
use App\Models\ApiKey;

class JobSecurityAndTenancyTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private Organization $orgA;
    private string $keyA;

    private User $userB;
    private Organization $orgB;
    private string $keyB;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->keyA = 'sk_' . Str::random(40);
        ApiKey::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgA->id,
            'created_by' => $this->userA->id,
            'name' => 'Key A',
            'key_hash' => hash('sha256', $this->keyA),
            'key_prefix' => substr($this->keyA, 0, 8),
            'scopes' => json_encode(['*']),
            'status' => 'ACTIVE'
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

        $this->keyB = 'sk_' . Str::random(40);
        ApiKey::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->orgB->id,
            'created_by' => $this->userB->id,
            'name' => 'Key B',
            'key_hash' => hash('sha256', $this->keyB),
            'key_prefix' => substr($this->keyB, 0, 8),
            'scopes' => json_encode(['*']),
            'status' => 'ACTIVE'
        ]);
    }

    /** User A cannot see User B jobs in index or show (IDOR protection) */
    public function test_user_a_cannot_see_user_b_jobs()
    {
        // Create job for User B
        $resB = $this->withHeader('Authorization', 'Bearer ' . $this->keyB)
            ->postJson('/api/v1/jobs', [
                'platform' => 'facebook',
                'operation' => 'posts',
                'target' => [
                    'type' => 'username',
                    'value' => 'user_b_target'
                ]
            ]);
        $jobIdB = $resB->json('data.job_id');

        // User A lists jobs
        $resA = $this->withHeader('Authorization', 'Bearer ' . $this->keyA)
            ->getJson('/api/v1/jobs');

        $resA->assertStatus(200);
        $ids = collect($resA->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($jobIdB));

        // User A tries to view User B job detail (must return 404 for IDOR resistance)
        $showRes = $this->withHeader('Authorization', 'Bearer ' . $this->keyA)
            ->getJson("/api/v1/jobs/{$jobIdB}");

        $showRes->assertStatus(404)
            ->assertJsonPath('error.code', 'JOB_NOT_FOUND');
    }

    /** Test exception leakage prevention */
    public function test_raw_exception_with_secret_does_not_leak_to_client()
    {
        // Submit unsupported platform
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->keyA)
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
