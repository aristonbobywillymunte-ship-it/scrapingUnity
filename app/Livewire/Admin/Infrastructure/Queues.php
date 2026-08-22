<?php
namespace App\Livewire\Admin\Infrastructure;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use App\Livewire\Admin\Concerns\AuthorizesAdmin;

#[Layout('layouts.app')]
class Queues extends Component {
    use WithPagination, AuthorizesAdmin;

    public $selectedRecord = null;

    public function mount() {
        $this->authorizeAdmin();
    }

    public function viewDetail($id) {
        $this->selectedRecord = DB::table('dead_letter_queue_records')
            ->join('runs', 'dead_letter_queue_records.run_id', '=', 'runs.id')
            ->select('dead_letter_queue_records.*', 'runs.origin', 'runs.capability')
            ->where('dead_letter_queue_records.id', $id)
            ->first();
    }

    public function closeDetail() {
        $this->selectedRecord = null;
    }

    public function render() {
        $queueDepth = 0;
        try {
            if (class_exists('\Redis')) {
                $redis = new \Redis();
                $redis->connect(config('database.redis.default.host', '127.0.0.1'), (int) config('database.redis.default.port', 6379), 1.0);
                $queueDepth = $redis->lLen('scrape:executions');
                $redis->close();
            }
        } catch (\Exception $e) {}

        $queues = [
            ['name' => 'scrape:executions', 'type' => 'Redis List', 'pending' => $queueDepth, 'target' => 'python_http_worker'],
            ['name' => 'default', 'type' => 'Database / Redis', 'pending' => DB::table('tasks')->where('status', 'QUEUED')->count(), 'target' => 'laravel_queue'],
        ];

        $dlqRecords = DB::table('dead_letter_queue_records')
            ->join('runs', 'dead_letter_queue_records.run_id', '=', 'runs.id')
            ->select('dead_letter_queue_records.*', 'runs.origin', 'runs.capability')
            ->orderBy('dead_letter_queue_records.failed_at', 'desc')
            ->paginate(10);

        return view('livewire.admin.infrastructure.queues', [
            'queues' => $queues,
            'dlqRecords' => $dlqRecords,
            'totalDlq' => DB::table('dead_letter_queue_records')->count(),
        ]);
    }
}
