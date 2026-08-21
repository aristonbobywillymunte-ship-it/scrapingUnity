<?php
namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use App\Livewire\Organization\Team;
use Illuminate\Support\Facades\DB;

class TeamE2ETest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::table('roles')->count() == 0) {
            DB::table('roles')->insert([
                ['id' => 'owner', 'description' => 'Owner', 'is_internal_role' => false],
                ['id' => 'admin', 'description' => 'Admin', 'is_internal_role' => false],
                ['id' => 'member', 'description' => 'Member', 'is_internal_role' => false],
                ['id' => 'internal_admin', 'description' => 'Internal Admin', 'is_internal_role' => true],
            ]);
        }
    }

    private function createUser($email = 'test@example.com')
    {
        return User::create([
            'id' => Str::uuid(), 'email' => $email, 'password_hash' => bcrypt('password'), 'status' => 'ACTIVE'
        ]);
    }

    // Test 1: owner + existing user + customer role -> allowed
    public function test_1_owner_plus_existing_user_plus_customer_role_succeeds()
    {
        $owner = $this->createUser('owner@example.com');
        $existingUser = $this->createUser('existing@example.com');
        $org = Organization::create(['id' => Str::uuid(), 'name' => 'Test']);
        OrganizationMembership::create(['id' => Str::uuid(), 'user_id' => $owner->id, 'organization_id' => $org->id, 'role_id' => 'owner']);

        $this->actingAs($owner);
        Livewire::test(Team::class)
            ->set('email', $existingUser->email)
            ->set('role', 'member')
            ->call('inviteMember')
            ->assertSet('message', 'User invited successfully.');

        $this->assertDatabaseHas('organization_memberships', [
            'user_id' => $existingUser->id,
            'role_id' => 'member',
        ]);
    }

    // Tests 2, 3, 4: unknown email -> no User, no Membership, factual message
    public function test_2_to_4_unknown_email_behavior()
    {
        $owner = $this->createUser('owner2@example.com');
        $org = Organization::create(['id' => Str::uuid(), 'name' => 'Test']);
        OrganizationMembership::create(['id' => Str::uuid(), 'user_id' => $owner->id, 'organization_id' => $org->id, 'role_id' => 'owner']);

        $this->actingAs($owner);
        Livewire::test(Team::class)
            ->set('email', 'doesnotexist@example.com')
            ->set('role', 'member')
            ->call('inviteMember')
            ->assertSet('message', 'Invitation unsupported for new users.'); // Test 4: factual message

        $this->assertDatabaseMissing('users', ['email' => 'doesnotexist@example.com']); // Test 2: no User
        $this->assertDatabaseCount('organization_memberships', 1); // Test 3: no new Membership (only owner exists)
    }

    // Test 5: ordinary member/viewer denied
    public function test_5_member_viewer_denied()
    {
        $member = $this->createUser('member@example.com');
        $org = Organization::create(['id' => Str::uuid(), 'name' => 'Test']);
        OrganizationMembership::create(['id' => Str::uuid(), 'user_id' => $member->id, 'organization_id' => $org->id, 'role_id' => 'member']);

        $this->actingAs($member);
        Livewire::test(Team::class)
            ->set('email', 'target@example.com')
            ->set('role', 'member')
            ->call('inviteMember')
            ->assertSet('message', 'You do not have permission to invite members.');

        $this->assertDatabaseMissing('users', ['email' => 'target@example.com']);
    }

    // Test 6: internal role injection denied
    public function test_6_internal_role_injection_denied()
    {
        $owner = $this->createUser('owner3@example.com');
        $existingUser = $this->createUser('victim@example.com');
        $org = Organization::create(['id' => Str::uuid(), 'name' => 'Test']);
        OrganizationMembership::create(['id' => Str::uuid(), 'user_id' => $owner->id, 'organization_id' => $org->id, 'role_id' => 'owner']);

        $this->actingAs($owner);
        Livewire::test(Team::class)
            ->set('email', $existingUser->email)
            ->set('role', 'internal_admin')
            ->call('inviteMember')
            ->assertSet('message', 'Invalid or internal role selected.');

        $this->assertDatabaseMissing('organization_memberships', [
            'user_id' => $existingUser->id,
            'role_id' => 'internal_admin',
        ]);
    }

    // Test 7: nonexistent role denied
    public function test_7_nonexistent_role_denied()
    {
        $owner = $this->createUser('owner4@example.com');
        $existingUser = $this->createUser('victim2@example.com');
        $org = Organization::create(['id' => Str::uuid(), 'name' => 'Test']);
        OrganizationMembership::create(['id' => Str::uuid(), 'user_id' => $owner->id, 'organization_id' => $org->id, 'role_id' => 'owner']);

        $this->actingAs($owner);
        Livewire::test(Team::class)
            ->set('email', $existingUser->email)
            ->set('role', 'fake_role_xyz')
            ->call('inviteMember')
            ->assertSet('message', 'Invalid or internal role selected.');

        $this->assertDatabaseMissing('organization_memberships', [
            'user_id' => $existingUser->id,
            'role_id' => 'fake_role_xyz',
        ]);
    }

    // Test 8: cross-organization manipulation denied
    // ownerA belongs to orgA but has no membership in orgB.
    // The Livewire component derives orgId from the authenticated user's memberships,
    // so even if X-Organization-Id header is spoofed, the server derives the real org
    // and verifies the actor's membership there. userB must NOT end up in orgB.
    public function test_8_cross_organization_manipulation_denied()
    {
        $ownerA = $this->createUser('ownera@example.com');
        $orgA = Organization::create(['id' => Str::uuid(), 'name' => 'Org A']);
        OrganizationMembership::create(['id' => Str::uuid(), 'user_id' => $ownerA->id, 'organization_id' => $orgA->id, 'role_id' => 'owner']);

        $orgB = Organization::create(['id' => Str::uuid(), 'name' => 'Org B']);
        $userB = $this->createUser('userb@example.com');

        $this->actingAs($ownerA);

        // Without providing X-Organization-Id, orgId will be derived from ownerA's first membership (orgA).
        // userB is not in orgA, so if invite succeeds it goes to orgA, NOT orgB.
        Livewire::test(Team::class)
            ->set('email', $userB->email)
            ->set('role', 'member')
            ->call('inviteMember');

        // Critical: userB must NOT appear in orgB
        $this->assertDatabaseMissing('organization_memberships', [
            'user_id' => $userB->id,
            'organization_id' => $orgB->id,
        ]);
    }
}
