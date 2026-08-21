<?php
namespace App\Services;
use App\Models\Run;
use App\Models\RunRequest;
use Illuminate\Support\Str;

class RunEngineService {
    public function createRun(string $orgId, string $capability, array $requestData) {
        if (!CapabilityRegistry::isValid($capability)) {
            throw new \Exception("Unsupported capability");
        }
        
        $run = Run::create([
            'id' => Str::uuid(),
            'organization_id' => $orgId,
            'capability' => $capability,
            'status' => 'QUEUED',
            'origin' => 'API',
            'created_at' => now()
        ]);
        
        RunRequest::create([
            'run_id' => $run->id,
            'target_type' => $requestData['target_type'] ?? null,
            'target_url' => $requestData['target_url'] ?? null
        ]);
        
        return $run;
    }
    
    public function cancelRun($run) {
        if ($run->status === 'COMPLETED' || $run->status === 'FAILED') {
            throw new \Exception("Invalid transition");
        }
        $run->status = 'CANCELLED';
        $run->cancel_requested_at = now();
        $run->save();
        return $run;
    }
    
    public function finalizeRun($run, $active, $completed, $failed, $cancelled) {
        if ($active > 0) throw new \Exception("Cannot finalize active run");
        
        if ($failed > 0 && $completed == 0) $run->status = 'FAILED';
        elseif ($failed > 0 && $completed > 0) $run->status = 'PARTIAL';
        elseif ($cancelled > 0 && $completed == 0 && $failed == 0) $run->status = 'CANCELLED';
        else $run->status = 'COMPLETED';
        
        $run->completed_at = now();
        $run->save();
        return $run;
    }
    
    public function getRunForOrganization($orgId, $runId) {
        return Run::where('organization_id', $orgId)->where('id', $runId)->first();
    }
}
