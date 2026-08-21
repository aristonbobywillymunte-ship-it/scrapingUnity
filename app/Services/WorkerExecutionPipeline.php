<?php
namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Facades\DB;
use App\Services\TaskEngineService;
use App\Services\RunEngineService;
use App\Services\RunResultService;
use Illuminate\Support\Str;
use Exception;
use App\Jobs\ExecuteScraperTask;

class WorkerExecutionPipeline {
    public function __construct(
        private TaskEngineService $taskEngine,
        private RunEngineService $runEngine,
        private RunResultService $resultService
    ) {}

    public function execute(string $taskId, string $workerIdentity = null) {
        $task = Task::find($taskId);
        if (!$task || !in_array($task->status, ['QUEUED', 'FAILED'])) return;

        $workerId = $workerIdentity ?? 'worker-'.Str::random(5);
        $attemptCount = $task->attempt_count + 1;

        $attemptId = Str::uuid();
        try {
            DB::table('task_attempts')->insert([
                'id' => $attemptId,
                'task_id' => $task->id,
                'run_id' => $task->run_id,
                'organization_id' => $task->organization_id,
                'attempt_number' => $attemptCount,
                'worker_identity' => $workerId,
                'outcome' => 'STARTED',
                'started_at' => now(),
            ]);
        } catch (\Exception $e) {}
        
        DB::table('tasks')->where('id', $task->id)->update(['attempt_count' => $attemptCount]);

        try {
            DB::transaction(function() use ($task, $workerId) {
                DB::table('tasks')->where('id', $task->id)->update([
                    'status' => 'LEASED',
                    'worker_identity' => $workerId
                ]);
                $task->refresh();
                $this->taskEngine->startTask($task);
            });
        } catch (Exception $e) { if ($e instanceof \Illuminate\Database\QueryException) dd($e->getMessage()); 
            return;
        }

        try {
            DB::transaction(function() use ($task, $attemptId, $workerId) {
                $capConfig = CapabilityRegistry::get($task->capability);
                if (!$capConfig) {
                    throw new Exception("Capability definition missing");
                }

                $workerClass = $capConfig['worker'];
                $collectorClass = $capConfig['collector'];

                $worker = app($workerClass);
                $collector = app($collectorClass);

                $records = $worker->run($collector, $task);

                foreach ($records as $record) {
                    $canonicalId = Str::uuid();
                    DB::table('canonical_entities')->insert([
                        'id' => $canonicalId,
                        'platform' => $record['platform'],
                        'entity_type' => $record['entity_type'],
                        'stable_source_id' => $record['stable_source_id'],
                        'normalized_url' => $record['normalized_url'],
                        'identity_hash' => hash('sha256', $record['platform'].':'.$record['entity_type'].':'.$record['stable_source_id']),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $payload = $record['payload'];
                    $payload['canonical_entity_id'] = $canonicalId;
                    $tableName = 'canonical_' . strtolower(Str::plural($record['entity_type']));
                    DB::table($tableName)->insert($payload);
                    $this->resultService->persist($task->run_id, $task->organization_id, $canonicalId, $task->id);
                }
                
                DB::table('task_attempts')->where('id', $attemptId)->update(['outcome' => 'COMPLETED', 'completed_at' => now()]);
                $this->taskEngine->completeTask($task);
                
                $run = \App\Models\Run::find($task->run_id);
                if ($run) {
                    $this->runEngine->finalizeRun($run, 0, 1, 0, 0);
                }
            });
        } catch (Exception $e) { if ($e instanceof \Illuminate\Database\QueryException) dd($e->getMessage()); 
            DB::table('task_attempts')->where('id', $attemptId)->update([
                'outcome' => 'FAILED', 
                'error_category' => 'internal_system',
                'error_code' => substr($e->getMessage(), 0, 255),
                'completed_at' => now()
            ]);
            
            $task->refresh();
            if ($task->attempt_count < 3) {
                DB::table('tasks')->where('id', $task->id)->update([
                    'status' => 'QUEUED',
                    'worker_identity' => null
                ]);
                dispatch(new ExecuteScraperTask($task->id));
            } else {
                $this->taskEngine->markFailed($task, 'internal_system', $e->getMessage());
                
                DB::table('failed_jobs')->insert([
                    'uuid' => Str::uuid(),
                    'connection' => 'database',
                    'queue' => 'default',
                    'payload' => json_encode(['task_id' => $task->id]),
                    'exception' => $e->getMessage(),
                    'failed_at' => now()
                ]);
                
                $run = \App\Models\Run::find($task->run_id);
                if ($run) {
                    $this->runEngine->finalizeRun($run, 0, 0, 1, 0);
                }
            }
        }
    }
}
