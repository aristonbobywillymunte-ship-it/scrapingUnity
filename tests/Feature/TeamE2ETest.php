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
        // check if roles table exists and seed if empty
        if (DB::table('roles')->count() == 0) {
            DB::table('roles')->insert([
                ['id' => 'owner', 'description' => 'Owner', 'is_internal_role' => false, ],
                ['id' => 'admin', 'description' => 'Admin', 'is_internal_role' => false, ],
                ['id' => 'member', 'description' => 'Member', 'is_internal_role' => false, ],
                ['id' => 'internal_admin', 'description' => 'Internal Admin', 'is_internal_role' => true, ],
            ]);
        }
    }

    private function createUser($email = 'test@example.com')
    {
        try {
            return User::factory()->create(['email' => $email]);
        } catch (\Exception $e) {
            return User::create([
                'id' => Str::uuid(),
                'email' => $email,
                'password_hash' => bcrypt('password'),
                'status' => 'ACTIVE'
            ]);
        }
    }

    public function test_owner_can_invite_existing_user()
    {
        $owner = $this->createUser('owner@example.com');
        $existingUser = $this->createUser('existing@example.com');
        
        $org = Organization::create(['id' => Str::uuid(), 'name' => 'Test']);
        OrganizationMembership::create([
            'id' => Str::uuid(),
            'user_id' => $owner->id,
            'organization_id' => $org->id,
            'role_id' => 'owner'
        ]);

        $this->actingAs($owner);

        Livewire::test(Team::class)
            ->set('email', $existingUser->email)
            ->set('role', 'member')
            ->call('inviteMember')
            ->assertSet('message', 'User invited successfully.');
            
        $this->assertDatabaseHas('organization_memberships', [
            'user_id' => $existingUser->id,
            'organization_id' => $org->id,
            'role_id' => 'member'
        ]);
    }

    public function test_unknown_user_invite_returns_simulated_message()
    {
        $owner = $this->createUser('owner2@example.com');
        $org = Organization::create(['id' => Str::uuid(), 'name' => 'Test']);
        OrganizationMembership::create([
            'id' => Str::uuid(),
            'user_id' => $owner->id,
            'organization_id' => $org->id,
            'role_id' => 'owner'
        ]);

        $this->actingAs($owner);

        Livewire::test(Team::class)
            ->set('email', 'doesnotexist@example.com')
            ->set('role', 'member')
            ->call('inviteMember')
            ->assertSet('message', 'Invitation unsupported for new users.');
            
        $this->assertDatabaseMissing('users', [
            'email' => 'doesnotexist@example.com'
        ]);
    }

    public function test_member_is_denied()
    {
        $member = $this->createUser('member@example.com');
        $org = Organization::create(['id' => Str::uuid(), 'name' => 'Test']);
        OrganizationMembership::create([
            'id' => Str::uuid(),
            'user_id' => $member->id,
            'organization_id' => $org->id,
            'role_id' => 'member'
        ]);

        $this->actingAs($member);

        Livewire::test(Team::class)
            ->set('email', 'test3@example.com')
            ->set('role', 'member')
            ->call('inviteMember')
            ->assertSet('message', 'You do not have permission to invite members.');
    }

    public function test_internal_role_injection_is_denied()
    {
        $owner = $this->createUser('owner3@example.com');
        $org = Organization::create(['id' => Str::uuid(), 'name' => 'Test']);
        OrganizationMembership::create([
            'id' => Str::uuid(),
            'user_id' => $owner->id,
            'organization_id' => $org->id,
            'role_id' => 'owner'
        ]);

        $this->actingAs($owner);

        Livewire::test(Team::class)
            ->set('email', 'test4@example.com')
            ->set('role', 'internal_admin')
            ->call('inviteMember')
            ->assertSet('message', 'Invalid or internal role selected.');
    }
}
