<?php
namespace App\Livewire\Admin\Parser;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use App\Livewire\Admin\Concerns\AuthorizesAdmin;

#[Layout('layouts.app')]
class Index extends Component {
    use WithPagination, AuthorizesAdmin;

    public $activeTab = 'versions'; // 'versions', 'failures', 'candidates'
    public $confirmingRollbackId = null;
    public $confirmingRollbackTag = null;
    public $successMessage = '';
    public $errorMessage = '';

    public function mount() {
        $this->authorizeAdmin();
    }

    public function setTab($tab) {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function requestRollback($versionId, $versionTag) {
        $this->authorizeAdmin();
        $this->confirmingRollbackId = $versionId;
        $this->confirmingRollbackTag = $versionTag;
    }

    public function confirmRollback() {
        $this->authorizeAdmin();
        if (!$this->confirmingRollbackId) return;

        try {
            DB::beginTransaction();
            $targetVersion = DB::table('selector_versions')->where('id', $this->confirmingRollbackId)->first();
            if ($targetVersion) {
                // Set all other versions for this selector to INACTIVE
                DB::table('selector_versions')
                    ->where('selector_id', $targetVersion->selector_id)
                    ->update(['status' => 'INACTIVE']);

                // Activate target version
                DB::table('selector_versions')
                    ->where('id', $this->confirmingRollbackId)
                    ->update(['status' => 'ACTIVE']);

                DB::table('audit_logs')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'actor_id' => auth()->id(),
                    'actor_type' => 'admin',
                    'action' => 'PARSER_ROLLBACK',
                    'target' => 'selector_versions:' . $this->confirmingRollbackId,
                    'safe_metadata' => json_encode(['version_tag' => $this->confirmingRollbackTag]),
                    'created_at' => now(),
                ]);
            }
            DB::commit();
            $this->successMessage = "Berhasil rollback ke versi parser {$this->confirmingRollbackTag}.";
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Admin::confirmRollback failed: ' . \App\Services\SanitizerService::sanitizeException($e));
            $this->errorMessage = 'Gagal melakukan rollback parser. Silakan periksa log sistem.';
        }

        $this->confirmingRollbackId = null;
        $this->confirmingRollbackTag = null;
    }

    public function cancelRollback() {
        $this->confirmingRollbackId = null;
        $this->confirmingRollbackTag = null;
    }

    public function render() {
        $versions = DB::table('selector_versions')
            ->join('selectors', 'selector_versions.selector_id', '=', 'selectors.id')
            ->select('selector_versions.*', 'selectors.platform', 'selectors.scraper', 'selectors.page_type')
            ->orderBy('selector_versions.created_at', 'desc')
            ->paginate(10);

        $failures = DB::table('parser_failures')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('livewire.admin.parser.index', [
            'versions' => $versions,
            'failures' => $failures,
            'counts' => [
                'versions' => DB::table('selector_versions')->count(),
                'failures' => DB::table('parser_failures')->count(),
                'active' => DB::table('selector_versions')->where('status', 'ACTIVE')->count(),
            ],
        ]);
    }
}
