<?php

namespace App\Livewire\Results;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class Show extends Component
{
    public $result;
    public $extractedData;

    public function mount($result)
    {
        $orgId = request()->header('X-Organization-Id') ?? auth()->user()->organizationMemberships()->first()?->organization_id;
        
        $res = DB::table('run_results')
            ->join('runs', 'run_results.run_id', '=', 'runs.id')
            ->join('canonical_entities', 'run_results.canonical_entity_id', '=', 'canonical_entities.id')
            ->where('run_results.id', $result)
            ->where('run_results.organization_id', $orgId)
            ->select(
                'run_results.*',
                'runs.capability',
                'canonical_entities.platform',
                'canonical_entities.entity_type',
                'canonical_entities.stable_source_id',
                'canonical_entities.normalized_url',
                'canonical_entities.identity_hash'
            )
            ->first();
            
        if (!$res) {
            abort(404);
        }
        
        $this->result = $res;

        // Fetch entity specific payload
        $tableName = 'canonical_' . strtolower(Str::plural($res->entity_type));
        $this->extractedData = DB::table($tableName)
            ->where('canonical_entity_id', $res->canonical_entity_id)
            ->first();
    }

    public function render()
    {
        return view('livewire.results.show');
    }
}
