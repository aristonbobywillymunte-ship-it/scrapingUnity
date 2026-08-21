<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use App\Livewire\Billing\Index;

class BillingE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_users_cannot_hit_add_credits()
    {
        // Check if user factory exists, else create manually
        try {
            $user = User::factory()->create();
        } catch (\Exception $e) {
            $user = User::create([
                'id' => Str::uuid(),
                'email' => 'test@example.com',
                'password_hash' => bcrypt('password'),
                'status' => 'ACTIVE'
            ]);
        }
        
        \Illuminate\Support\Facades\DB::table('roles')->insertOrIgnore([['id' => 'owner', 'description' => 'Owner', 'is_internal_role' => false]]);
        $org = Organization::create(['id' => Str::uuid(), 'name' => 'Test']);
        OrganizationMembership::create([
            'id' => Str::uuid(),
            'user_id' => $user->id,
            'organization_id' => $org->id,
            'role_id' => 'owner'
        ]);

        $this->actingAs($user);

        Livewire::test(Index::class)
            ->assertDontSee('Add 100 Credits');
            
        $this->assertFalse(method_exists(Index::class, 'addCredits'));
    }
}
