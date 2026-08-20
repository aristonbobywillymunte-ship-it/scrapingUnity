<?php
namespace App\Services;

use App\Models\Task;
use App\Models\Run;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class TaskEngineService {
    private TaskTransitionService $transitionService;

    public function __construct(TaskTransitionService $transitionService) {
        $this->transitionService = $transitionService;
    }

    private function generateDeterministicId(string $runId, string $capability): string {
        // v5 UUID based on run_id and capability
        return Uuid::uuid5(Uuid::NAMESPACE_OID, $runId . ':' . $capability)->toString();
    }

    public function createTask(string $runId, string $orgId, string $capability, array $options = []): Task {
        $run = Run::where('id', $runId)->where('organization_id', $orgId)->first();
        if (!$run) {
            throw new \Exception("Run not found or organization mismatch");
        }
        
        if (in_array($run->status, ['COMPLETED', 'FAILED', 'CANCELLED'])) {
            throw new \Exception("Cannot create task for a terminal run");
        }

        $id = $this->generateDeterministicId($runId, $capability);

        try {
            return Task::create([
                'id' => $id,
                'run_id' => $runId,
                'organization_id' => $orgId,
                'capability' => $capability,
                'status' => Task::STATUS_QUEUED,
                'attempt_count' => 0,
                'queued_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == 23505) { // Unique constraint violation
                throw new \Exception("Duplicate task");
            }
            throw $e;
        }
    }

    public function getTaskForOrganization(string $orgId, string $taskId): Task {
        $task = Task::where('id', $taskId)->where('organization_id', $orgId)->first();
        if (!$task) {
            throw new \Exception("Task not found or organization mismatch");
        }
        return $task;
    }

    public function markLeased(Task $task, string $workerIdentity): Task {
        $this->transitionService->validateTransition($task->status, Task::STATUS_LEASED);
        $task->status = Task::STATUS_LEASED;
        $task->worker_identity = $workerIdentity;
        $task->save();
        return $task;
    }

    public function startTask(Task $task): Task {
        $this->transitionService->validateTransition($task->status, Task::STATUS_RUNNING);
        $task->status = Task::STATUS_RUNNING;
        $task->started_at = now();
        $task->save();
        return $task;
    }

    public function completeTask(Task $task): Task {
        $this->transitionService->validateTransition($task->status, Task::STATUS_COMPLETED);
        $task->status = Task::STATUS_COMPLETED;
        $task->completed_at = now();
        $task->save();
        return $task;
    }

    public function markFailed(Task $task, string $errorCategory, string $errorCode = null): Task {
        $this->transitionService->validateTransition($task->status, Task::STATUS_FAILED);
        $task->status = Task::STATUS_FAILED;
        $task->completed_at = now();
        $task->error_category = $errorCategory;
        $task->error_code = $errorCode;
        $task->save();
        return $task;
    }

    public function cancelTask(Task $task): Task {
        $this->transitionService->validateTransition($task->status, Task::STATUS_CANCELLED);
        $task->status = Task::STATUS_CANCELLED;
        $task->completed_at = now();
        $task->save();
        return $task;
    }
}
