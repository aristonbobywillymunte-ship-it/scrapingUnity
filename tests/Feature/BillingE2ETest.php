<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Livewire\Billing\Index;

class BillingE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_users_cannot_hit_add_credits()
    {
        $user = User::create([
            'id' => Str::uuid(),
            'email' => 'test@example.com',
            'password_hash' => bcrypt('password'),
            'status' => 'ACTIVE'
        ]);

        DB::table('roles')->insertOrIgnore([['id' => 'owner', 'description' => 'Owner', 'is_internal_role' => false]]);
        $org = Organization::create(['id' => Str::uuid(), 'name' => 'Test']);
        OrganizationMembership::create([
            'id' => Str::uuid(),
            'user_id' => $user->id,
            'organization_id' => $org->id,
            'role_id' => 'owner'
        ]);

        // Setup initial credit state
        $lotId = Str::uuid();
        DB::table('credit_lots')->insert([
            'id' => $lotId,
            'organization_id' => $org->id,
            'original_quantity' => 500,
            'remaining_quantity' => 500,
            'source' => 'TOP_UP',
            'expires_at' => now()->addYear()
        ]);
        DB::table('credit_ledger')->insert([
            'id' => Str::uuid(),
            'organization_id' => $org->id,
            'transaction_type' => 'PURCHASE',
            'credit_lot_id' => $lotId,
            'quantity' => 500,
            'created_at' => now(),
            'event_idempotency_key' => Str::uuid()
        ]);

        $initialLotsCount = DB::table('credit_lots')->count();
        $initialLedgerCount = DB::table('credit_ledger')->count();
        $initialBalance = DB::table('credit_lots')->sum('remaining_quantity');

        $this->actingAs($user);

        // Render the page
        $component = Livewire::test(Index::class)
            ->assertDontSee('Add 100 Credits')
            ->assertSee('500.00');

        // Assert absence of addCredits
        $this->assertFalse(method_exists(Index::class, 'addCredits'));

        // Assert no state changed
        $this->assertEquals($initialLotsCount, DB::table('credit_lots')->count());
        $this->assertEquals($initialLedgerCount, DB::table('credit_ledger')->count());
        $this->assertEquals($initialBalance, DB::table('credit_lots')->sum('remaining_quantity'));

        // Attempt to call missing method
        try {
            $component->call('addCredits');
        } catch (\Exception $e) {
            // Expected
        }

        // Still unchanged
        $this->assertEquals($initialLotsCount, DB::table('credit_lots')->count());
        $this->assertEquals($initialLedgerCount, DB::table('credit_ledger')->count());
    }
}
