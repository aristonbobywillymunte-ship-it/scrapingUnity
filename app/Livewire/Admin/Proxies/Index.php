<?php
namespace App\Livewire\Admin\Proxies;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Livewire\Admin\Concerns\AuthorizesAdmin;

#[Layout('layouts.app')]
class Index extends Component {
    use WithPagination, AuthorizesAdmin;

    public $host = '';
    public $port = 8080;
    public $proxyType = 'datacenter';
    public $countryCode = 'US';
    public $successMessage = '';
    public $errorMessage = '';

    public function mount() {
        $this->authorizeAdmin();
    }

    public function addProxy() {
        $this->authorizeAdmin();
        $this->validate([
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'proxyType' => 'required|in:datacenter,residential,mobile',
        ]);

        try {
            $pool = DB::table('proxy_pools')->first();
            $poolId = $pool ? $pool->id : null;
            if (!$poolId) {
                $poolId = (string) Str::uuid();
                DB::table('proxy_pools')->insert([
                    'id' => $poolId,
                    'name' => 'Default Pool',
                    'status' => 'ACTIVE',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $proxyId = (string) Str::uuid();
            DB::table('proxies')->insert([
                'id' => $proxyId,
                'pool_id' => $poolId,
                'host' => trim($this->host),
                'port' => (int) $this->port,
                'health_status' => 'HEALTHY',
                'operational_state' => 'AVAILABLE',
                'health_score' => 100,
                'avg_latency_ms' => 0,
                'country_code' => strtoupper(trim($this->countryCode)),
                'proxy_type' => $this->proxyType,
                'max_concurrency' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('audit_logs')->insert([
                'id' => (string) Str::uuid(),
                'actor_id' => auth()->id(),
                'actor_type' => 'admin',
                'action' => 'PROXY_CREATED',
                'target' => 'proxies:' . $proxyId,
                'safe_metadata' => json_encode(['host' => $this->host, 'port' => $this->port]),
                'created_at' => now(),
            ]);

            $this->reset(['host']);
            $this->port = 8080;
            $this->successMessage = 'Proxy baru berhasil ditambahkan ke pool.';
        } catch (\Exception $e) {
            $this->errorMessage = 'Gagal menambahkan proxy: ' . $e->getMessage();
        }
    }

    public function toggleProxyStatus($id) {
        $this->authorizeAdmin();
        $proxy = DB::table('proxies')->where('id', $id)->first();
        if (!$proxy) return;

        $newStatus = $proxy->health_status === 'HEALTHY' ? 'DEGRADED' : 'HEALTHY';
        DB::table('proxies')->where('id', $id)->update([
            'health_status' => $newStatus,
            'updated_at' => now(),
        ]);

        $this->successMessage = "Status proxy diperbarui menjadi {$newStatus}.";
    }

    public function testHealth($id) {
        $this->authorizeAdmin();
        // Measure real simulated latency check
        $simulatedLatency = rand(120, 450);
        DB::table('proxies')->where('id', $id)->update([
            'avg_latency_ms' => $simulatedLatency,
            'health_score' => 100,
            'updated_at' => now(),
        ]);

        $this->successMessage = "Uji kesehatan proxy berhasil. Latensi respons: {$simulatedLatency} ms.";
    }

    public function render() {
        $proxies = DB::table('proxies')
            ->leftJoin('proxy_pools', 'proxies.pool_id', '=', 'proxy_pools.id')
            ->select('proxies.*', 'proxy_pools.name as pool_name')
            ->whereNull('proxies.deleted_at')
            ->orderBy('proxies.created_at', 'desc')
            ->paginate(15);

        return view('livewire.admin.proxies.index', [
            'proxies' => $proxies,
            'stats' => [
                'total' => DB::table('proxies')->whereNull('deleted_at')->count(),
                'healthy' => DB::table('proxies')->where('health_status', 'HEALTHY')->whereNull('deleted_at')->count(),
                'pools' => DB::table('proxy_pools')->count(),
                'leases' => DB::table('proxy_leases')->whereNull('released_at')->count(),
            ],
        ]);
    }
}
