<?php
namespace App\Livewire\Admin\Jobs;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use App\Livewire\Admin\Concerns\AuthorizesAdmin;

#[Layout('layouts.app')]
class Index extends Component {
    use WithPagination, AuthorizesAdmin;

    public $statusFilter = '';
    public $originFilter = '';
    public $search = '';

    public function mount() {
        $this->authorizeAdmin();
    }

    public function render() {
        $query = DB::table('runs')
            ->leftJoin('organizations', 'runs.organization_id', '=', 'organizations.id')
            ->select('runs.*', 'organizations.name as organization_name');

        if (!empty($this->statusFilter)) {
            $query->where('runs.status', $this->statusFilter);
        }
        if (!empty($this->originFilter)) {
            $query->where('runs.origin', $this->originFilter);
        }
        if (!empty($this->search)) {
            $query->where('runs.id', 'like', '%' . $this->search . '%');
        }

        $jobs = $query->orderBy('runs.created_at', 'desc')->paginate(15);

        return view('livewire.admin.jobs.index', [
            'jobs' => $jobs,
            'stats' => [
                'total' => DB::table('runs')->count(),
                'completed' => DB::table('runs')->where('status', 'COMPLETED')->count(),
                'failed' => DB::table('runs')->where('status', 'FAILED')->count(),
                'processing' => DB::table('runs')->whereIn('status', ['RUNNING', 'QUEUED'])->count(),
            ],
        ]);
    }
}
