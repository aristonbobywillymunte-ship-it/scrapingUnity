<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Exception;

class BillingService {

    /**
     * Reserve credits for a run. Idempotent: if a reservation already exists
     * for this run_id (enforced by unique index), returns the existing reservation id.
     */
    public function reserveCredits(string $orgId, float $amount, string $runId): string {
        // Check for existing reservation first (idempotency read path)
        $existing = DB::table('billing_reservations')->where('run_id', $runId)->first();
        if ($existing) {
            return $existing->id;
        }

        return DB::transaction(function () use ($orgId, $amount, $runId) {
            $lots = DB::table('credit_lots')
                ->where('organization_id', $orgId)
                ->where('remaining_quantity', '>', 0)
                ->lockForUpdate()
                ->orderBy('expires_at', 'asc')
                ->get();

            $remainingToReserve = $amount;
            $usedLots = [];

            foreach ($lots as $lot) {
                if ($remainingToReserve <= 0) break;
                $take = min($lot->remaining_quantity, $remainingToReserve);
                DB::table('credit_lots')->where('id', $lot->id)->decrement('remaining_quantity', $take);
                $remainingToReserve -= $take;
                $usedLots[] = ['lot_id' => $lot->id, 'amount' => $take];
            }

            if ($remainingToReserve > 0) {
                throw new Exception("Insufficient credits for reservation");
            }

            $reservationId = Str::uuid()->toString();

            try {
                DB::table('billing_reservations')->insert([
                    'id'              => $reservationId,
                    'run_id'          => $runId,
                    'organization_id' => $orgId,
                    'estimated'       => $amount,
                    'reserved'        => $amount,
                    'settled'         => 0,
                    'released'        => 0,
                    'status'          => 'PENDING',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            } catch (QueryException $e) {
                // Unique constraint on run_id: another request already reserved — idempotent return
                if (str_contains($e->getMessage(), 'billing_reservations_run_id_key')) {
                    $existing = DB::table('billing_reservations')->where('run_id', $runId)->first();
                    return $existing->id;
                }
                throw $e;
            }

            foreach ($usedLots as $used) {
                $idempotencyKey = 'reserve:' . $runId . ':' . $used['lot_id'];
                try {
                    DB::table('credit_ledger')->insert([
                        'id'                    => Str::uuid(),
                        'organization_id'       => $orgId,
                        'transaction_type'      => 'RESERVE',
                        'credit_lot_id'         => $used['lot_id'],
                        'reservation_id'        => $reservationId,
                        'run_id'                => $runId,
                        'quantity'              => -$used['amount'],
                        'created_at'            => now(),
                        'event_idempotency_key' => $idempotencyKey,
                    ]);
                } catch (QueryException $e) {
                    // Duplicate ledger entry — idempotent, skip
                    if (!str_contains($e->getMessage(), 'credit_ledger_event_idempotency_key_key')) {
                        throw $e;
                    }
                }
            }

            return $reservationId;
        });
    }

    /**
     * Settle a reservation. Idempotent: SETTLED reservations are no-ops.
     * Cannot settle a RELEASED reservation.
     */
    public function settleReservation(string $reservationId, float $actualAmount): void {
        DB::transaction(function () use ($reservationId, $actualAmount) {
            $reservation = DB::table('billing_reservations')
                ->where('id', $reservationId)
                ->lockForUpdate()
                ->first();

            if (!$reservation) throw new Exception("Reservation not found: $reservationId");
            if ($reservation->status === 'SETTLED') return; // idempotent
            if ($reservation->status === 'RELEASED') throw new Exception("Cannot settle a RELEASED reservation");

            DB::table('billing_reservations')->where('id', $reservationId)->update([
                'status'     => 'SETTLED',
                'settled'    => $actualAmount,
                'updated_at' => now(),
            ]);

            // Find the original RESERVE entries to know which lots to reference
            $reserveEntries = DB::table('credit_ledger')
                ->where('reservation_id', $reservationId)
                ->where('transaction_type', 'RESERVE')
                ->get();

            foreach ($reserveEntries as $reserveEntry) {
                $idempotencyKey = 'settle:' . $reservationId . ':' . $reserveEntry->credit_lot_id;
                try {
                    DB::table('credit_ledger')->insert([
                        'id'                    => Str::uuid(),
                        'organization_id'       => $reservation->organization_id,
                        'transaction_type'      => 'USAGE',
                        'credit_lot_id'         => $reserveEntry->credit_lot_id,
                        'reservation_id'        => $reservationId,
                        'run_id'                => $reservation->run_id,
                        'quantity'              => $reserveEntry->quantity, // already negative
                        'created_at'            => now(),
                        'event_idempotency_key' => $idempotencyKey,
                    ]);
                } catch (QueryException $e) {
                    if (!str_contains($e->getMessage(), 'credit_ledger_event_idempotency_key_key')) {
                        throw $e;
                    }
                }
            }
        });
    }

    /**
     * Release (refund) a reservation. Idempotent: RELEASED reservations are no-ops.
     * Cannot release a SETTLED reservation.
     */
    public function releaseReservation(string $reservationId): void {
        DB::transaction(function () use ($reservationId) {
            $reservation = DB::table('billing_reservations')
                ->where('id', $reservationId)
                ->lockForUpdate()
                ->first();

            if (!$reservation) throw new Exception("Reservation not found: $reservationId");
            if ($reservation->status === 'RELEASED') return; // idempotent
            if ($reservation->status === 'SETTLED') throw new Exception("Cannot release a SETTLED reservation");

            // Restore credits to lots (FEFO in reverse: restore to the lots we took from)
            $reserveEntries = DB::table('credit_ledger')
                ->where('reservation_id', $reservationId)
                ->where('transaction_type', 'RESERVE')
                ->get();

            foreach ($reserveEntries as $entry) {
                DB::table('credit_lots')
                    ->where('id', $entry->credit_lot_id)
                    ->increment('remaining_quantity', abs($entry->quantity));
            }

            DB::table('billing_reservations')->where('id', $reservationId)->update([
                'status'     => 'RELEASED',
                'released'   => $reservation->reserved,
                'updated_at' => now(),
            ]);

            foreach ($reserveEntries as $entry) {
                $idempotencyKey = 'release:' . $reservationId . ':' . $entry->credit_lot_id;
                try {
                    DB::table('credit_ledger')->insert([
                        'id'                    => Str::uuid(),
                        'organization_id'       => $reservation->organization_id,
                        'transaction_type'      => 'RELEASE',
                        'credit_lot_id'         => $entry->credit_lot_id,
                        'reservation_id'        => $reservationId,
                        'run_id'                => $reservation->run_id,
                        'quantity'              => abs($entry->quantity), // positive = returned
                        'created_at'            => now(),
                        'event_idempotency_key' => $idempotencyKey,
                    ]);
                } catch (QueryException $e) {
                    if (!str_contains($e->getMessage(), 'credit_ledger_event_idempotency_key_key')) {
                        throw $e;
                    }
                }
            }
        });
    }
}
