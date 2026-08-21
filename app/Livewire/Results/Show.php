<?php

namespace App\Livewire\Results;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class Show extends Component
{
    public $result;

    public function mount($result)
    {
        $orgId = request()->header('X-Organization-Id') ?? auth()->user()->organizationMemberships()->first()?->organization_id;
        
        $res = DB::table('canonical_results')
            ->join('runs', 'canonical_results.run_id', '=', 'runs.id')
            ->where('canonical_results.id', $result)
            ->where('runs.organization_id', $orgId)
            ->select('canonical_results.*', 'runs.capability')
            ->first();
            
        if (!$res) {
            abort(404);
        }
        
        $this->result = $res;
    }

    public function render()
    {
        return view('livewire.results.show');
    }
}
