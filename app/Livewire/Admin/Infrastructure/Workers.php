<?php
namespace App\Livewire\Admin\Infrastructure;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Livewire\Admin\Concerns\AuthorizesAdmin;

#[Layout('layouts.app')]
class Workers extends Component {
    use AuthorizesAdmin;

    public function mount() {
        $this->authorizeAdmin();
    }

    public function render() {
        $workers = [];
        try {
            if (class_exists('\Redis')) {
                $redis = new \Redis();
                $redis->connect(config('database.redis.default.host', '127.0.0.1'), (int) config('database.redis.default.port', 6379), 1.0);
                
                // Inspect registered worker heartbeats
                $hb = $redis->get('worker:heartbeat:python_http_1');
                $workers[] = [
                    'id' => 'python_http_1',
                    'type' => 'Python HTTP Worker',
                    'status' => $hb ? 'ONLINE' : 'OFFLINE',
                    'last_heartbeat' => $hb ?? 'N/A',
                    'concurrency' => 2,
                    'active_jobs' => 0,
                ];

                $browserHb = $redis->get('worker:heartbeat:python_browser_1');
                $workers[] = [
                    'id' => 'python_browser_1',
                    'type' => 'Python Browser Worker (Playwright)',
                    'status' => $browserHb ? 'ONLINE' : 'OFFLINE',
                    'last_heartbeat' => $browserHb ?? 'N/A',
                    'concurrency' => 1,
                    'active_jobs' => 0,
                ];

                $redis->close();
            }
        } catch (\Exception $e) {}

        if (empty($workers)) {
            $workers = [
                ['id' => 'python_http_1', 'type' => 'Python HTTP Worker', 'status' => 'OFFLINE', 'last_heartbeat' => 'N/A', 'concurrency' => 2, 'active_jobs' => 0],
                ['id' => 'python_browser_1', 'type' => 'Python Browser Worker', 'status' => 'OFFLINE', 'last_heartbeat' => 'N/A', 'concurrency' => 1, 'active_jobs' => 0],
            ];
        }

        return view('livewire.admin.infrastructure.workers', [
            'workers' => $workers,
        ]);
    }
}
