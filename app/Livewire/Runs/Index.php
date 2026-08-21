<?php

namespace App\Livewire\Runs;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Run;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public function render()
    {
        $orgId = request()->header('X-Organization-Id') ?? auth()->user()->organizationMemberships()->first()?->organization_id;
        
        $runs = collect();
        if ($orgId) {
            $runs = Run::where('organization_id', $orgId)->orderBy('created_at', 'desc')->paginate(10);
        }

        return view('livewire.runs.index', [
            'runs' => $runs
        ]);
    }
}
