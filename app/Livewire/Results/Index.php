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
            $results = DB::table('run_results')
                ->join('runs', 'run_results.run_id', '=', 'runs.id')
                ->join('canonical_entities', 'run_results.canonical_entity_id', '=', 'canonical_entities.id')
                ->where('run_results.organization_id', $orgId)
                ->select(
                    'run_results.id',
                    'run_results.run_id',
                    'run_results.created_at',
                    'runs.capability',
                    'canonical_entities.platform',
                    'canonical_entities.entity_type',
                    'canonical_entities.normalized_url'
                )
                ->orderBy('run_results.created_at', 'desc')
                ->paginate(10);
        }

        return view('livewire.results.index', [
            'results' => $results
        ]);
    }
}
