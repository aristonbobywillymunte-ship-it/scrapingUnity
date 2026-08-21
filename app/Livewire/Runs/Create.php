<?php
namespace App\Livewire\Runs;

use Livewire\Component;
use App\Services\CapabilityRegistry;
use App\Services\RunOrchestrationService;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class Create extends Component {
    public $capability = 'facebook_posts';
    public $discovery_mode = 'search_query'; // search_query | hashtag | target
    
    // Dynamic fields
    public $search_query = '';
    public $hashtag = '';
    public $target = '';
    public $target_url = ''; // fallback backwards compatibility
    public $max_pages = 1;
    
    public $error = '';

    public function mount() {
        $this->updateDiscoveryModeForCapability();
    }

    public function updatedCapability() {
        $this->updateDiscoveryModeForCapability();
        $this->error = '';
    }

    private function updateDiscoveryModeForCapability() {
        $capConfig = CapabilityRegistry::get($this->capability);
        if (!$capConfig) return;

        $modes = $capConfig['supported_modes'] ?? ['target'];
        if (!in_array($this->discovery_mode, $modes)) {
            $this->discovery_mode = $modes[0];
        }
    }

    public function getCapabilitiesProperty() {
        return CapabilityRegistry::all();
    }

    public function getCurrentCapabilityProperty() {
        return CapabilityRegistry::get($this->capability);
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

        $supportedModes = $capConfig['supported_modes'] ?? ['target'];
        if (!in_array($this->discovery_mode, $supportedModes)) {
            $this->error = "Mode pencarian {$this->discovery_mode} tidak didukung untuk capability {$this->capability}.";
            return;
        }

        // Backward compatibility: if target_url was passed but search_query/target empty
        if (!empty($this->target_url)) {
            if ($this->discovery_mode === 'search_query' && empty($this->search_query)) {
                $this->search_query = $this->target_url;
            } elseif ($this->discovery_mode === 'target' && empty($this->target)) {
                $this->target = $this->target_url;
            }
        }

        // Server-side dynamic validation
        $rules = [];
        if ($this->discovery_mode === 'search_query') {
            $rules['search_query'] = 'required|string|min:2|max:255';
        } elseif ($this->discovery_mode === 'hashtag') {
            $rules['hashtag'] = 'required|string|min:2|max:100';
        } elseif ($this->discovery_mode === 'target') {
            $rules['target'] = 'required|string|min:3';
        }
        $rules['max_pages'] = 'required|integer|min:1|max:100';

        $this->validate($rules);

        // Normalize hashtag (strip leading # for clean storage, or keep normalized)
        $normalizedHashtag = null;
        if ($this->discovery_mode === 'hashtag') {
            $normalizedHashtag = ltrim(trim($this->hashtag), '#');
            if (empty($normalizedHashtag)) {
                $this->error = 'Hashtag tidak valid.';
                return;
            }
        }

        try {
            $payload = [
                'discovery_mode' => $this->discovery_mode,
                'search_query' => $this->discovery_mode === 'search_query' ? trim($this->search_query) : null,
                'hashtag' => $normalizedHashtag,
                'target' => $this->discovery_mode === 'target' ? trim($this->target) : null,
                'target_url' => $this->discovery_mode === 'target' ? trim($this->target) : ($this->target_url ?: null),
                'max_pages' => (int)$this->max_pages
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
