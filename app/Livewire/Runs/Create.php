<?php
namespace App\Livewire\Runs;
use Livewire\Component;
use App\Services\CapabilityRegistry;
use App\Services\RunOrchestrationService;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
class Create extends Component {
    public $capability = 'facebook_posts';
    
    // Dynamic fields
    public $target_url = '';
    public $max_pages = 1;
    public $search_query = '';
    public $keyword = '';
    
    public $error = '';

    public function getCapabilitiesProperty() {
        return CapabilityRegistry::all();
    }

    public function submit(RunOrchestrationService $orchestration) {
        $orgId = request()->header('X-Organization-Id') ?? auth()->user()->organizationMemberships()->first()?->organization_id;
        
        if (!$orgId) {
            $this->error = 'No organization selected.';
            return;
        }

        $capConfig = CapabilityRegistry::get($this->capability);
        if (!$capConfig) {
            $this->error = 'Invalid capability.';
            return;
        }

        // Validate dynamically
        $rules = [];
        if (in_array('target_url', $capConfig['fields'])) $rules['target_url'] = 'required|url';
        if (in_array('search_query', $capConfig['fields'])) $rules['search_query'] = 'required|string';
        if (in_array('keyword', $capConfig['fields'])) $rules['keyword'] = 'required|string';
        if (in_array('max_pages', $capConfig['fields'])) $rules['max_pages'] = 'required|integer|min:1';
        
        $this->validate($rules);

        try {
            $payload = [
                'target_url' => $this->target_url,
                'search_query' => $this->search_query,
                'keyword' => $this->keyword,
                'max_pages' => $this->max_pages
            ];
            
            $run = $orchestration->submitRun(auth()->user(), $orgId, $this->capability, $payload);
            
            return redirect()->route('runs.show', $run->id);
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render() {
        return view('livewire.runs.create');
    }
}
