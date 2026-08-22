<?php
namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use App\Services\SanitizerService;

#[Layout('layouts.app')]
class Operations extends Component {
    // Scraping Lab inputs
    public $labPlatform = 'facebook';
    public $labOperation = 'profile';
    public $labTarget = 'zuck';
    public $labMode = 'search_query';
    public $labMaxItems = 10;
    public $labExecutionMode = 'auto'; // auto, http_only, browser_only
    public $labSuccessMessage = '';
    public $labErrorMessage = '';
    public $labResultPreview = null;

    public function mount() {
        $this->authorizeAdmin();
    }

    // P0-4: Canonical DB-backed Admin check — no hardcoded email bypass
    private function authorizeAdmin(): void {
        $user = auth()->user();
        if (!$user) {
            abort(403, 'Unauthorized access.');
        }
        $isAdmin = DB::table('internal_user_assignments')
            ->where('user_id', $user->id)
            ->exists();
        if (!$isAdmin) {
            abort(403, 'Unauthorized access.');
        }
    }

    public function runScrapingLab() {
        $this->authorizeAdmin();

        $this->validate([
            'labPlatform' => 'required|in:facebook',
            'labOperation' => 'required|in:profile,single_post,profile_posts,replies,search_posts',
            'labTarget' => 'required|string|min:1',
            'labMaxItems' => 'required|integer|min:1|max:100',
        ]);

        try {
            $executionId = (string) Str::uuid();

            // Build real task payload
            $taskPayload = [
                'execution_id' => $executionId,
                'platform' => $this->labPlatform,
                'operation' => $this->labOperation,
                'target' => trim($this->labTarget),
                'mode' => $this->labMode,
                'max_items' => (int) $this->labMaxItems,
                'origin' => 'MANUAL_LAB',
                'created_at' => now()->toIso8601String(),
            ];

            // Push to Redis queue
            $redisHost = config('database.redis.default.host', '127.0.0.1');
            $redisPort = config('database.redis.default.port', 6379);

            if (class_exists('\Redis')) {
                $redisClient = new \Redis();
                $redisClient->connect($redisHost, (int) $redisPort, 2.0);
                $redisClient->rPush('scrape:executions', json_encode($taskPayload));
                $redisClient->close();

                $this->labSuccessMessage = "Pekerjaan Lab berhasil dikirim ke antrian Redis (Execution ID: {$executionId}).";
                $this->labErrorMessage = '';
                $this->labResultPreview = [
                    'execution_id' => $executionId,
                    'status' => 'DISPATCHED_TO_REDIS',
                    'queue' => 'scrape:executions',
                    'worker_target' => 'python_http_worker',
                    'payload' => $taskPayload,
                ];
            } else {
                \Illuminate\Support\Facades\Redis::rpush('scrape:executions', json_encode($taskPayload));
                $this->labSuccessMessage = "Pekerjaan Lab berhasil dikirim ke antrian Redis (Execution ID: {$executionId}).";
                $this->labErrorMessage = '';
                $this->labResultPreview = [
                    'execution_id' => $executionId,
                    'status' => 'DISPATCHED_TO_REDIS',
                    'queue' => 'scrape:executions',
                    'worker_target' => 'python_http_worker',
                    'payload' => $taskPayload,
                ];
            }
        } catch (\Exception $e) {
            $this->labErrorMessage = "Kesalahan Scraping Lab: " . SanitizerService::sanitizeException($e);
            $this->labSuccessMessage = '';
        }
    }

    public function render() {
        $failedJobs = 0;
        try {
            $failedJobs = DB::table('dead_letter_queue_records')->count();
        } catch(\Exception $e) {
            \Illuminate\Support\Facades\Log::error(SanitizerService::sanitizeException($e));
        }

        $pendingTasks = 0;
        try {
            $pendingTasks = DB::table('tasks')->where('status', 'QUEUED')->count();
        } catch(\Exception $e) {
            \Illuminate\Support\Facades\Log::error(SanitizerService::sanitizeException($e));
        }

        // Factual worker heartbeat check
        $workerStatus = 'OFFLINE';
        $workerHeartbeatTime = 'N/A';
        try {
            if (class_exists('\Redis')) {
                $redisClient = new \Redis();
                $redisClient->connect(config('database.redis.default.host', '127.0.0.1'), (int) config('database.redis.default.port', 6379), 1.0);
                $hb = $redisClient->get('worker:heartbeat:python_http_1');
                if ($hb) {
                    $workerStatus = 'ACTIVE / ONLINE';
                    $workerHeartbeatTime = $hb;
                }
                $redisClient->close();
            }
        } catch (\Exception $e) {
            // worker check failed
        }

        // Factual Proxy inventory
        $proxies = [];
        try {
            $proxies = DB::table('proxies')->orderBy('created_at', 'desc')->limit(5)->get();
        } catch (\Exception $e) {}

        // Factual Audit logs
        $auditLogs = [];
        try {
            $auditLogs = DB::table('audit_logs')->orderBy('created_at', 'desc')->limit(5)->get();
        } catch (\Exception $e) {}

        return view('livewire.admin.operations', [
            'failedJobs' => $failedJobs,
            'pendingTasks' => $pendingTasks,
            'workerStatus' => $workerStatus,
            'workerHeartbeatTime' => $workerHeartbeatTime,
            'proxies' => $proxies,
            'auditLogs' => $auditLogs,
        ]);
    }
}
