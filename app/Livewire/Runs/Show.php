<?php

namespace App\Livewire\Runs;

use Livewire\Component;
use App\Models\Run;
use App\Services\RunEngineService;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class Show extends Component
{
    public $run;
    public $error = '';

    public function mount(Run $run)
    {
        $orgId = request()->header('X-Organization-Id') ?? auth()->user()->organizationMemberships()->first()?->organization_id;
        if ($run->organization_id !== $orgId) {
            abort(403);
        }
        $this->run = $run;
    }

    public function cancelRun(RunEngineService $service)
    {
        try {
            $this->run = $service->cancelRun($this->run);
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.runs.show');
    }
}
