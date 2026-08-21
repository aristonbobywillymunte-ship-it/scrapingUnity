<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Run;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render()
    {
        $orgId = request()->header('X-Organization-Id') ?? auth()->user()->organizationMemberships()->first()?->organization_id;
        
        $activeRunsCount = 0;
        $completedRunsCount = 0;
        $failedRunsCount = 0;
        $creditUsage = 0;
        
        if ($orgId) {
            $activeRunsCount = Run::where('organization_id', $orgId)->whereIn('status', ['QUEUED', 'RUNNING'])->count();
            $completedRunsCount = Run::where('organization_id', $orgId)->where('status', 'COMPLETED')->count();
            $failedRunsCount = Run::where('organization_id', $orgId)->where('status', 'FAILED')->count();
            $creditUsage = DB::table('credit_ledger')
                ->where('organization_id', $orgId)
                ->where('transaction_type', 'USAGE')
                ->sum('quantity');
        }

        return view('livewire.dashboard', [
            'activeRunsCount' => $activeRunsCount,
            'completedRunsCount' => $completedRunsCount,
            'failedRunsCount' => $failedRunsCount,
            'creditUsage' => abs($creditUsage)
        ]);
    }
}
