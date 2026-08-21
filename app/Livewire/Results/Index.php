<?php

namespace App\Livewire\Results;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public function render()
    {
        $orgId = request()->header('X-Organization-Id') ?? auth()->user()->organizationMemberships()->first()?->organization_id;
        
        $results = collect();
        if ($orgId) {
            $results = DB::table('canonical_results')
                ->join('runs', 'canonical_results.run_id', '=', 'runs.id')
                ->where('runs.organization_id', $orgId)
                ->select('canonical_results.*', 'runs.capability')
                ->orderBy('canonical_results.created_at', 'desc')
                ->paginate(10);
        }

        return view('livewire.results.index', [
            'results' => $results
        ]);
    }
}
