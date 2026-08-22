<?php
namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use App\Livewire\Admin\Concerns\AuthorizesAdmin;

#[Layout('layouts.app')]
class Dashboard extends Component {
    use AuthorizesAdmin;

    public function mount() {
        $this->authorizeAdmin();
    }

    public function render() {
        $totalUsers = DB::table('users')->count();
        $totalRuns = DB::table('runs')->count();
        $totalResults = DB::table('run_results')->count();
        $failedJobs = DB::table('dead_letter_queue_records')->count();
        $pendingTasks = DB::table('tasks')->where('status', 'QUEUED')->count();
        $totalProxies = DB::table('proxies')->whereNull('deleted_at')->count();
        $healthyProxies = DB::table('proxies')->where('health_status', 'HEALTHY')->whereNull('deleted_at')->count();
        $totalParserFailures = DB::table('parser_failures')->count();

        // Worker heartbeat check
        $workerOnline = false;
        $workerHb = 'N/A';
        try {
            if (class_exists('\Redis')) {
                $redis = new \Redis();
                $redis->connect(config('database.redis.default.host', '127.0.0.1'), (int) config('database.redis.default.port', 6379), 1.0);
                $hb = $redis->get('worker:heartbeat:python_http_1');
                if ($hb) {
                    $workerOnline = true;
                    $workerHb = $hb;
                }
                $redis->close();
            }
        } catch (\Exception $e) {}

        $recentRuns = DB::table('runs')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentAudits = DB::table('audit_logs')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('livewire.admin.dashboard', [
            'totalUsers' => $totalUsers,
            'totalRuns' => $totalRuns,
            'totalResults' => $totalResults,
            'failedJobs' => $failedJobs,
            'pendingTasks' => $pendingTasks,
            'totalProxies' => $totalProxies,
            'healthyProxies' => $healthyProxies,
            'totalParserFailures' => $totalParserFailures,
            'workerOnline' => $workerOnline,
            'workerHb' => $workerHb,
            'recentRuns' => $recentRuns,
            'recentAudits' => $recentAudits,
        ]);
    }
}
