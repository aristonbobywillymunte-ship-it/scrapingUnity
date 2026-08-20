<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RunResultService {
    public function persist($runId, $orgId, $canonicalEntityId, $sourceTaskId) {
        $run = DB::table('runs')->where('id', $runId)->where('organization_id', $orgId)->first();
        if (!$run) throw new \Exception('Invalid run or organization');
        
        // Ensure source task belongs to run
        $task = DB::table('tasks')->where('id', $sourceTaskId)->where('run_id', $runId)->first();
        if (!$task) throw new \Exception('Task does not belong to run');
        
        $exists = DB::table('run_results')
            ->where('run_id', $runId)
            ->where('canonical_entity_id', $canonicalEntityId)
            ->exists();
        if ($exists) throw new \Exception('Duplicate result');
        
        DB::table('run_results')->insert([
            'id' => Str::uuid(),
            'run_id' => $runId,
            'organization_id' => $orgId,
            'canonical_entity_id' => $canonicalEntityId,
            'source_task_id' => $sourceTaskId,
            'created_at' => now()
        ]);
    }
    
    public function get($orgId, $id) {
        return DB::table('run_results')->where('id', $id)->where('organization_id', $orgId)->first();
    }
}
