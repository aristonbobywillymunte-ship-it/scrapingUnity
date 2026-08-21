<?php
namespace App\Livewire\Billing;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Illuminate\Support\Str;

#[Layout('layouts.app')]
class Index extends Component
{
    public $amount = 100;


    public function render()
    {
        $orgId = request()->header('X-Organization-Id') ?? auth()->user()->organizationMemberships()->first()?->organization_id;
        
        $balance = 0;
        $transactions = collect();
        
        if ($orgId) {
            $balance = DB::table('credit_lots')->where('organization_id', $orgId)->sum('remaining_quantity');
            $transactions = DB::table('credit_ledger')
                ->where('organization_id', $orgId)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();
        }

        return view('livewire.billing.index', [
            'balance' => $balance,
            'transactions' => $transactions
        ]);
    }
}
