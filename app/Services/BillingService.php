<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class BillingService {
    public function reserveCredits(string $orgId, float $amount, string $runId): string {
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
            
            $usedLots[] = [
                'lot_id' => $lot->id,
                'amount' => $take
            ];
        }
        
        if ($remainingToReserve > 0) {
            throw new Exception("Insufficient credits for reservation");
        }
        
        $reservationId = Str::uuid();
        DB::table('billing_reservations')->insert([
            'id' => $reservationId,
            'run_id' => $runId,
            'organization_id' => $orgId,
            'estimated' => $amount,
            'reserved' => $amount,
            'settled' => 0,
            'released' => 0,
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Log transaction for each lot taken
        foreach ($usedLots as $used) {
            DB::table('credit_ledger')->insert([
                'id' => Str::uuid(),
                'organization_id' => $orgId,
                'transaction_type' => 'RESERVE',
                'credit_lot_id' => $used['lot_id'],
                'reservation_id' => $reservationId,
                'run_id' => $runId,
                'quantity' => -$used['amount'],
                'created_at' => now(),
                'event_idempotency_key' => Str::uuid()
            ]);
        }
        
        return $reservationId;
    }
}
