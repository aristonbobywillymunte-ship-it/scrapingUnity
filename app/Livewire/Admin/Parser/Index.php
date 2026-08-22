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

    public function generateCandidate($failureId) {
        $this->authorizeAdmin();
        $failure = DB::table('parser_failures')->where('id', $failureId)->first();
        if (!$failure) return;

        try {
            $candidateId = (string) \Illuminate\Support\Str::uuid();
            $suggestedSelectors = [
                'post_container' => 'div[data-testid="post_container"], article, div[role="article"]',
                'author_name' => 'h2 strong, h3 strong, a[role="link"] strong',
                'text_content' => 'div[data-ad-preview="message"], div[dir="auto"]',
                'timestamp' => 'abbr[data-utime], a[aria-label] time',
            ];

            $openAiKey = config('services.openai.api_key') ?? env('OPENAI_API_KEY');
            
            if (!empty($openAiKey)) {
                $aiProvider = 'OPENAI';
                $aiModel = 'gpt-4o';
                // Perform actual provider request
                $response = \Illuminate\Support\Facades\Http::withToken($openAiKey)
                    ->timeout(15)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => $aiModel,
                        'messages' => [
                            [
                                'role' => 'system', 
                                'content' => 'You are an HTML parsing expert. Extract optimal CSS selectors for Facebook based on the error context.'
                            ],
                            [
                                'role' => 'user', 
                                'content' => "Generate CSS selectors for these missing fields: {$failure->missing_fields}"
                            ]
                        ],
                        'response_format' => ['type' => 'json_object']
                    ]);
                    
                if ($response->successful()) {
                    $json = $response->json('choices.0.message.content');
                    $parsed = json_decode($json, true);
                    if (is_array($parsed)) {
                        $suggestedSelectors = array_merge($suggestedSelectors, $parsed);
                    }
                } else {
                    $aiProvider = 'OPENAI_FAILED';
                }
            } else {
                $aiProvider = 'LOCAL_HEURISTIC';
                $aiModel = 'heuristic_v1';
            }

            DB::table('parser_ai_candidates')->insert([
                'id' => $candidateId,
                'failure_id' => $failureId,
                'platform' => $failure->platform,
                'operation' => $failure->operation,
                'base_version' => $failure->parser_version ?? 'v1',
                'candidate_selectors' => json_encode($suggestedSelectors),
                'ai_provider' => $aiProvider,
                'ai_model' => $aiModel,
                'status' => 'PENDING',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->successMessage = "Kandidat perbaikan selector berhasil dibuat (Provider: {$aiProvider}, Status: PENDING).";
            $this->activeTab = 'candidates';
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Admin::generateCandidate failed: ' . \App\Services\SanitizerService::sanitizeException($e));
            $this->errorMessage = 'Gagal membuat kandidat perbaikan selector.';
        }
    }

    public function validateCandidate($candidateId) {
        $this->authorizeAdmin();
        $candidate = DB::table('parser_ai_candidates')->where('id', $candidateId)->first();
        if (!$candidate) return;

        try {
            // Execute real Python Validation Engine
            $selectorsJson = $candidate->candidate_selectors;
            $scriptPath = base_path('python_scraper/validator.py');
            $escapedInput = escapeshellarg($selectorsJson);
            $pythonCmd = "python3 {$scriptPath} {$escapedInput}";

            $output = @shell_exec($pythonCmd);
            $validation = json_decode($output, true);

            if (!$validation || !isset($validation['is_valid'])) {
                // In-process fallback MUST FAIL if python fails
                $validation = [
                    'is_valid' => false,
                    'coverage_score' => 0.0,
                    'field_results' => [],
                    'validator_engine' => 'PYTHON_VALIDATOR_FAILED'
                ];
                $status = 'FAILED';
                $isValid = false;
                $coveragePct = 0;
            } else {
                $isValid = $validation['is_valid'] ?? false;
                $coveragePct = intval(($validation['coverage_score'] ?? 0) * 100);
                $status = $isValid ? 'VALID' : 'INVALID';
            }

            DB::table('parser_ai_candidates')->where('id', $candidateId)->update([
                'status' => $status,
                'validation_results' => json_encode($validation),
                'updated_at' => now(),
            ]);

            DB::table('parser_validation_runs')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'candidate_id' => $candidateId,
                'platform' => $candidate->platform,
                'operation' => $candidate->operation,
                'parser_version' => $candidate->base_version,
                'validator_engine' => 'PYTHON',
                'is_valid' => $isValid,
                'coverage_score' => $validation['coverage_score'] ?? 0.0,
                'field_results' => json_encode($validation['field_results'] ?? []),
                'validation_output' => $output,
                'run_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->successMessage = "Validasi Python selesai: Status " . ($isValid ? 'VALID' : 'INVALID') . " (Coverage: {$coveragePct}%).";
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Admin::validateCandidate failed: ' . \App\Services\SanitizerService::sanitizeException($e));
            $this->errorMessage = 'Gagal memvalidasi kandidat selector.';
        }
    }

    public function approveCandidate($candidateId) {
        $this->authorizeAdmin();
        $candidate = DB::table('parser_ai_candidates')->where('id', $candidateId)->first();
        if (!$candidate || $candidate->status !== 'VALID') {
            $this->errorMessage = 'Hanya kandidat dengan status VALID yang dapat disetujui untuk aktivasi.';
            return;
        }

        try {
            DB::beginTransaction();

            // Find or create parent selector record
            $selector = DB::table('selectors')
                ->where('platform', strtolower($candidate->platform))
                ->first();

            $selectorId = $selector ? $selector->id : (string) \Illuminate\Support\Str::uuid();
            if (!$selector) {
                DB::table('selectors')->insert([
                    'id' => $selectorId,
                    'platform' => strtolower($candidate->platform),
                    'scraper' => 'posts',
                    'source' => 'html',
                    'page_type' => 'post',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Deactivate existing active versions
            DB::table('selector_versions')
                ->where('selector_id', $selectorId)
                ->update(['status' => 'INACTIVE']);

            // Create new active selector version
            $newVersionId = (string) \Illuminate\Support\Str::uuid();
            $newTag = 'v' . date('Ymd.His');
            DB::table('selector_versions')->insert([
                'id' => $newVersionId,
                'selector_id' => $selectorId,
                'status' => 'ACTIVE',
                'version_tag' => $newTag,
                'selector_data' => $candidate->candidate_selectors,
                'test_metadata' => $candidate->validation_results,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update candidate record
            DB::table('parser_ai_candidates')->where('id', $candidateId)->update([
                'status' => 'APPROVED',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'updated_at' => now(),
            ]);

            // Audit log
            DB::table('audit_logs')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'actor_id' => auth()->id(),
                'actor_type' => 'admin',
                'action' => 'PARSER_AI_CANDIDATE_APPROVED',
                'target' => 'selector_versions:' . $newVersionId,
                'safe_metadata' => json_encode([
                    'candidate_id' => $candidateId,
                    'version_tag' => $newTag,
                    'platform' => $candidate->platform,
                ]),
                'created_at' => now(),
            ]);

            DB::commit();
            $this->successMessage = "Kandidat AI berhasil disetujui & diaktifkan sebagai versi parser {$newTag}.";
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Admin::approveCandidate failed: ' . \App\Services\SanitizerService::sanitizeException($e));
            $this->errorMessage = 'Gagal menyetujui dan mengaktifkan kandidat parser.';
        }
    }

    public function rejectCandidate($candidateId, $reason = 'Kandidat ditolak oleh Admin.') {
        $this->authorizeAdmin();
        try {
            DB::table('parser_ai_candidates')->where('id', $candidateId)->update([
                'status' => 'REJECTED',
                'rejection_reason' => $reason,
                'updated_at' => now(),
            ]);
            $this->successMessage = "Kandidat parser berhasil ditolak.";
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Admin::rejectCandidate failed: ' . \App\Services\SanitizerService::sanitizeException($e));
            $this->errorMessage = 'Gagal menolak kandidat parser.';
        }
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

        $candidates = DB::table('parser_ai_candidates')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('livewire.admin.parser.index', [
            'versions' => $versions,
            'failures' => $failures,
            'candidates' => $candidates,
            'counts' => [
                'versions' => DB::table('selector_versions')->count(),
                'failures' => DB::table('parser_failures')->count(),
                'candidates' => DB::table('parser_ai_candidates')->count(),
                'active' => DB::table('selector_versions')->where('status', 'ACTIVE')->count(),
            ],
        ]);
    }
}
