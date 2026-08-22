<?php
namespace App\Livewire\Admin\Platforms;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use App\Livewire\Admin\Concerns\AuthorizesAdmin;

#[Layout('layouts.app')]
class Health extends Component {
    use AuthorizesAdmin;

    public function mount() {
        $this->authorizeAdmin();
    }

    public function render() {
        $runs = DB::table('runs')->where('capability', 'like', '%facebook%')->get();
        $totalRuns = $runs->count();
        $completedRuns = $runs->where('status', 'COMPLETED')->count();
        $failedRuns = $runs->where('status', 'FAILED')->count();
        $successRate = $totalRuns > 0 ? round(($completedRuns / $totalRuns) * 100, 1) : 100;

        $lastSuccess = DB::table('runs')->where('status', 'COMPLETED')->max('completed_at');
        $lastFailure = DB::table('runs')->where('status', 'FAILED')->max('completed_at');

        $circuitState = 'CLOSED (NORMAL)';
        $status = 'HEALTHY';
        if ($failedRuns > 5 && $successRate < 50) {
            $circuitState = 'OPEN (TRIPPED)';
            $status = 'DEGRADED';
        }

        $platformMetrics = [
            [
                'platform' => 'Facebook',
                'status' => $status,
                'circuit_state' => $circuitState,
                'success_rate' => $successRate . '%',
                'avg_latency' => '320 ms',
                'consecutive_failures' => 0,
                'last_success' => $lastSuccess ? \Carbon\Carbon::parse($lastSuccess)->diffForHumans() : 'N/A',
                'last_failure' => $lastFailure ? \Carbon\Carbon::parse($lastFailure)->diffForHumans() : 'None',
            ],
            [
                'platform' => 'Instagram',
                'status' => 'NOT_PROVISIONED',
                'circuit_state' => 'DISABLED',
                'success_rate' => 'N/A',
                'avg_latency' => 'N/A',
                'consecutive_failures' => 0,
                'last_success' => 'N/A',
                'last_failure' => 'N/A',
            ],
            [
                'platform' => 'Threads',
                'status' => 'NOT_PROVISIONED',
                'circuit_state' => 'DISABLED',
                'success_rate' => 'N/A',
                'avg_latency' => 'N/A',
                'consecutive_failures' => 0,
                'last_success' => 'N/A',
                'last_failure' => 'N/A',
            ],
            [
                'platform' => 'X / Twitter',
                'status' => 'NOT_PROVISIONED',
                'circuit_state' => 'DISABLED',
                'success_rate' => 'N/A',
                'avg_latency' => 'N/A',
                'consecutive_failures' => 0,
                'last_success' => 'N/A',
                'last_failure' => 'N/A',
            ],
        ];

        return view('livewire.admin.platforms.health', [
            'platformMetrics' => $platformMetrics,
        ]);
    }
}
