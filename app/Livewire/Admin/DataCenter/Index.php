<?php
namespace App\Livewire\Admin\DataCenter;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use App\Livewire\Admin\Concerns\AuthorizesAdmin;

#[Layout('layouts.app')]
class Index extends Component {
    use WithPagination, AuthorizesAdmin;

    public $activeTab = 'all'; // 'all', 'api', 'manual', 'diagnostic'
    public $search = '';
    public $platformFilter = '';
    public $selectedResult = null;

    public function mount() {
        $this->authorizeAdmin();
    }

    public function setTab($tab) {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function viewDetail($id) {
        $this->selectedResult = DB::table('run_results')
            ->join('canonical_entities', 'run_results.canonical_entity_id', '=', 'canonical_entities.id')
            ->leftJoin('canonical_posts', 'canonical_entities.id', '=', 'canonical_posts.canonical_entity_id')
            ->join('runs', 'run_results.run_id', '=', 'runs.id')
            ->select(
                'run_results.*',
                'canonical_entities.platform',
                'canonical_entities.entity_type',
                'canonical_entities.normalized_url',
                'canonical_entities.identity_hash',
                'canonical_posts.text_content',
                'canonical_posts.published_at',
                'canonical_posts.safe_metadata',
                'runs.origin',
                'runs.capability'
            )
            ->where('run_results.id', $id)
            ->first();
    }

    public function closeDetail() {
        $this->selectedResult = null;
    }

    public function render() {
        if ($this->activeTab === 'diagnostic') {
            $query = DB::table('dead_letter_queue_records')
                ->join('runs', 'dead_letter_queue_records.run_id', '=', 'runs.id')
                ->select('dead_letter_queue_records.*', 'runs.origin', 'runs.capability');

            if (!empty($this->search)) {
                $query->where('dead_letter_queue_records.error_code', 'like', '%' . $this->search . '%');
            }

            $items = $query->orderBy('dead_letter_queue_records.failed_at', 'desc')->paginate(15);

            return view('livewire.admin.data-center.index', [
                'diagnosticItems' => $items,
                'results' => null,
            ]);
        }

        $query = DB::table('run_results')
            ->join('canonical_entities', 'run_results.canonical_entity_id', '=', 'canonical_entities.id')
            ->leftJoin('canonical_posts', 'canonical_entities.id', '=', 'canonical_posts.canonical_entity_id')
            ->join('runs', 'run_results.run_id', '=', 'runs.id')
            ->select(
                'run_results.id',
                'run_results.created_at',
                'run_results.billable_status',
                'canonical_entities.platform',
                'canonical_entities.entity_type',
                'canonical_entities.normalized_url',
                'canonical_posts.text_content',
                'runs.origin',
                'runs.capability'
            );

        if ($this->activeTab === 'api') {
            $query->where('runs.origin', 'API');
        } elseif ($this->activeTab === 'manual') {
            $query->whereIn('runs.origin', ['MANUAL', 'MANUAL_LAB', 'LAB']);
        }

        if (!empty($this->platformFilter)) {
            $query->where('canonical_entities.platform', $this->platformFilter);
        }

        if (!empty($this->search)) {
            $query->where(function($q) {
                $q->where('canonical_posts.text_content', 'like', '%' . $this->search . '%')
                  ->orWhere('canonical_entities.normalized_url', 'like', '%' . $this->search . '%');
            });
        }

        $results = $query->orderBy('run_results.created_at', 'desc')->paginate(15);

        return view('livewire.admin.data-center.index', [
            'results' => $results,
            'diagnosticItems' => null,
            'counts' => [
                'all' => DB::table('run_results')->count(),
                'api' => DB::table('run_results')->join('runs', 'run_results.run_id', '=', 'runs.id')->where('runs.origin', 'API')->count(),
                'manual' => DB::table('run_results')->join('runs', 'run_results.run_id', '=', 'runs.id')->whereIn('runs.origin', ['MANUAL', 'MANUAL_LAB', 'LAB'])->count(),
                'diagnostic' => DB::table('dead_letter_queue_records')->count(),
            ],
        ]);
    }
}
