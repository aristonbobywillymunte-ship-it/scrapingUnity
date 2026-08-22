<?php
namespace App\Livewire\Admin\Plans;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Livewire\Admin\Concerns\AuthorizesAdmin;

#[Layout('layouts.app')]
class Index extends Component {
    use AuthorizesAdmin;

    public $name = '';
    public $durationDays = 30;
    public $retentionDays = 30;
    public $monthlyQuota = 1000;
    public $rateLimitRpm = 60;
    public $maxConcurrency = 2;
    public $allowedPlatform = 'facebook';
    public $successMessage = '';
    public $errorMessage = '';

    public function mount() {
        $this->authorizeAdmin();
    }

    public function createPlan() {
        $this->authorizeAdmin();
        $this->validate([
            'name' => 'required|string|max:100',
            'durationDays' => 'required|integer|min:1',
            'monthlyQuota' => 'required|integer|min:0',
        ]);

        try {
            $packageId = (string) Str::uuid();
            DB::table('packages')->insert([
                'id' => $packageId,
                'name' => trim($this->name),
                'is_custom' => false,
                'status' => 'ACTIVE',
                'duration_days' => (int) $this->durationDays,
                'retention_days' => (int) $this->retentionDays,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('package_entitlements')->insert([
                'id' => (string) Str::uuid(),
                'package_id' => $packageId,
                'capability' => $this->allowedPlatform . '_posts',
                'limits' => json_encode([
                    'monthly_quota' => (int) $this->monthlyQuota,
                    'rate_limit_rpm' => (int) $this->rateLimitRpm,
                    'max_concurrency' => (int) $this->maxConcurrency,
                ]),
            ]);

            $this->reset(['name']);
            $this->successMessage = 'Paket/Plan baru berhasil dibuat.';
        } catch (\Exception $e) {
            $this->errorMessage = 'Gagal membuat plan: ' . $e->getMessage();
        }
    }

    public function render() {
        $plans = DB::table('packages')
            ->leftJoin('package_entitlements', 'packages.id', '=', 'package_entitlements.package_id')
            ->select('packages.*', 'package_entitlements.capability', 'package_entitlements.limits')
            ->orderBy('packages.created_at', 'desc')
            ->get();

        $creditStats = [
            'total_lots' => DB::table('credit_lots')->count(),
            'total_allocated' => DB::table('credit_lots')->sum('original_quantity'),
            'total_remaining' => DB::table('credit_lots')->sum('remaining_quantity'),
        ];

        return view('livewire.admin.plans.index', [
            'plans' => $plans,
            'creditStats' => $creditStats,
        ]);
    }
}
