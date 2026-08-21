<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\BillingService;

class FinancialIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private BillingService $billing;
    private string $orgId;
    private string $lotId;
    private string $runId;  // shared run for simple tests

    protected function setUp(): void
    {
        parent::setUp();
        $this->billing = app(BillingService::class);
        $this->orgId   = Str::uuid()->toString();
        $this->lotId   = Str::uuid()->toString();
        $this->runId   = Str::uuid()->toString();

        // FK chain: organizations -> runs -> credit_lots
        DB::table('organizations')->insert([
            'id'     => $this->orgId,
            'name'   => 'Billing Test Org',
            'status' => 'ACTIVE',
        ]);

        DB::table('credit_lots')->insert([
            'id'                 => $this->lotId,
            'organization_id'    => $this->orgId,
            'original_quantity'  => 1000,
            'remaining_quantity' => 1000,
            'source'             => 'TOP_UP',
            'expires_at'         => now()->addYear(),
        ]);
    }

    /** Create a run row for the given runId and orgId (billing_reservations requires real run FK) */
    private function mkRun(string $runId, string $orgId): void
    {
        DB::table('runs')->insert([
            'id'              => $runId,
            'organization_id' => $orgId,
            'capability'      => 'facebook_posts',
            'status'          => 'QUEUED',
        ]);
    }

    // -----------------------------------------------------------------------
    // A1 — Reservation idempotency
    // -----------------------------------------------------------------------
    public function test_A1_reservation_is_idempotent()
    {
        $this->mkRun($this->runId, $this->orgId);

        $id1 = $this->billing->reserveCredits($this->orgId, 10, $this->runId);
        $id2 = $this->billing->reserveCredits($this->orgId, 10, $this->runId); // must be no-op

        $this->assertSame($id1, $id2, 'Both calls must return the same reservation id');

        $reservations = DB::table('billing_reservations')->where('run_id', $this->runId)->get();
        $this->assertCount(1, $reservations, 'Only one reservation row must exist');

        $reserveEntries = DB::table('credit_ledger')
            ->where('run_id', $this->runId)
            ->where('transaction_type', 'RESERVE')
            ->get();
        $this->assertCount(1, $reserveEntries, 'Only one RESERVE ledger entry must exist');

        $balance = DB::table('credit_lots')->where('id', $this->lotId)->value('remaining_quantity');
        $this->assertEquals(990, $balance, 'Balance decremented exactly once (not twice)');
    }

    // -----------------------------------------------------------------------
    // A2 — Settlement idempotency
    // -----------------------------------------------------------------------
    public function test_A2_settlement_is_idempotent()
    {
        $runId = Str::uuid()->toString();
        $this->mkRun($runId, $this->orgId);
        $reservationId = $this->billing->reserveCredits($this->orgId, 10, $runId);

        $this->billing->settleReservation($reservationId, 8);
        $this->billing->settleReservation($reservationId, 8); // second call must be no-op

        $usageEntries = DB::table('credit_ledger')
            ->where('reservation_id', $reservationId)
            ->where('transaction_type', 'USAGE')
            ->get();
        $this->assertCount(1, $usageEntries, 'Only one USAGE ledger entry must exist');

        $status = DB::table('billing_reservations')->where('id', $reservationId)->value('status');
        $this->assertEquals('SETTLED', $status);
    }

    // -----------------------------------------------------------------------
    // A3 — Release idempotency
    // -----------------------------------------------------------------------
    public function test_A3_release_is_idempotent()
    {
        $runId = Str::uuid()->toString();
        $this->mkRun($runId, $this->orgId);
        $reservationId = $this->billing->reserveCredits($this->orgId, 10, $runId);

        $this->billing->releaseReservation($reservationId);
        $this->billing->releaseReservation($reservationId); // second call must be no-op

        $balanceAfter = DB::table('credit_lots')->where('id', $this->lotId)->value('remaining_quantity');
        $this->assertEquals(1000, $balanceAfter, 'Credits must be fully restored exactly once');

        $releaseEntries = DB::table('credit_ledger')
            ->where('reservation_id', $reservationId)
            ->where('transaction_type', 'RELEASE')
            ->get();
        $this->assertCount(1, $releaseEntries, 'Only one RELEASE ledger entry must exist');

        $status = DB::table('billing_reservations')->where('id', $reservationId)->value('status');
        $this->assertEquals('RELEASED', $status);
    }

    // -----------------------------------------------------------------------
    // A4a — Invalid transition: SETTLED -> RELEASED must throw
    // -----------------------------------------------------------------------
    public function test_A4a_settled_to_released_throws()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Cannot release a SETTLED/');

        $runId = Str::uuid()->toString();
        $this->mkRun($runId, $this->orgId);
        $reservationId = $this->billing->reserveCredits($this->orgId, 10, $runId);
        $this->billing->settleReservation($reservationId, 10);
        $this->billing->releaseReservation($reservationId); // must throw
    }

    // -----------------------------------------------------------------------
    // A4b — Invalid transition: RELEASED -> SETTLED must throw
    // -----------------------------------------------------------------------
    public function test_A4b_released_to_settled_throws()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Cannot settle a RELEASED/');

        $runId = Str::uuid()->toString();
        $this->mkRun($runId, $this->orgId);
        $reservationId = $this->billing->reserveCredits($this->orgId, 10, $runId);
        $this->billing->releaseReservation($reservationId);
        $this->billing->settleReservation($reservationId, 10); // must throw
    }

    // -----------------------------------------------------------------------
    // A5 — Overspend protection
    // -----------------------------------------------------------------------
    public function test_A5_overspend_protection()
    {
        $runId = Str::uuid()->toString();
        $this->mkRun($runId, $this->orgId);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Insufficient credits/');

        $this->billing->reserveCredits($this->orgId, 9999, $runId);
    }

    // -----------------------------------------------------------------------
    // A5b — Financial invariant: initial = remaining + settled + reserved(outstanding)
    // -----------------------------------------------------------------------
    public function test_A5b_financial_invariant_holds()
    {
        $runId1 = Str::uuid()->toString();
        $runId2 = Str::uuid()->toString();
        $runId3 = Str::uuid()->toString();
        $this->mkRun($runId1, $this->orgId);
        $this->mkRun($runId2, $this->orgId);
        $this->mkRun($runId3, $this->orgId);

        $r1 = $this->billing->reserveCredits($this->orgId, 100, $runId1);
        $r2 = $this->billing->reserveCredits($this->orgId, 200, $runId2);
        $r3 = $this->billing->reserveCredits($this->orgId, 300, $runId3);

        $this->billing->settleReservation($r1, 100);  // consumed 100
        $this->billing->releaseReservation($r2);       // restored 200, r3 still reserved 300

        $remaining = DB::table('credit_lots')->where('id', $this->lotId)->value('remaining_quantity');
        // 1000 initial - 100(settled) - 300(still reserved) = 600
        $this->assertEquals(600, $remaining, 'Financial invariant: 1000 - 100(settled) - 300(reserved) = 600');
    }
}
