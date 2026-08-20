<?php
namespace App\Services;

use App\Models\Task;
use App\Models\TaskLease;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TaskLeaseService {
    private TaskTransitionService $transitionService;

    public function __construct(TaskTransitionService $transitionService) {
        $this->transitionService = $transitionService;
    }

    public function acquire(Task $task, string $workerIdentity, int $ttlSeconds = 300): ?TaskLease {
        return DB::transaction(function () use ($task, $workerIdentity, $ttlSeconds) {
            $lockedTask = Task::where('id', $task->id)->lockForUpdate()->first();
            
            if (!in_array($lockedTask->status, ['QUEUED', 'RETRY_WAIT'])) {
                throw new \Exception("Task is not leaseable");
            }
            
            // Check active lease
            $activeLease = TaskLease::where('task_id', $lockedTask->id)
                ->whereNull('released_at')
                ->where('status', 'ACTIVE')
                ->where('expires_at', '>', now())
                ->first();
                
            if ($activeLease) {
                return null; // Rejected, already active
            }
            
            $lease = TaskLease::create([
                'id' => Str::uuid(),
                'task_id' => $lockedTask->id,
                'worker_identity' => $workerIdentity,
                'acquired_at' => now(),
                'expires_at' => now()->addSeconds($ttlSeconds),
                'status' => 'ACTIVE'
            ]);
            
            $this->transitionService->validateTransition($lockedTask->status, Task::STATUS_LEASED);
            $lockedTask->status = Task::STATUS_LEASED;
            $lockedTask->active_lease_id = $lease->id;
            $lockedTask->lease_expires_at = $lease->expires_at;
            $lockedTask->save();
            
            return $lease;
        });
    }

    public function heartbeat(TaskLease $lease, int $ttlSeconds = 300): TaskLease {
        if ($lease->released_at !== null || $lease->status !== 'ACTIVE') {
            throw new \Exception("Cannot heartbeat a released or inactive lease");
        }
        if ($lease->expires_at < now()) {
            throw new \Exception("Cannot heartbeat an expired lease");
        }
        
        $lease->heartbeat_at = now();
        $lease->expires_at = now()->addSeconds($ttlSeconds);
        $lease->save();
        
        // Update task expiry as well
        $task = Task::find($lease->task_id);
        if ($task && $task->active_lease_id === $lease->id) {
            $task->heartbeat_at = $lease->heartbeat_at;
            $task->lease_expires_at = $lease->expires_at;
            $task->save();
        }
        
        return $lease;
    }

    public function release(TaskLease $lease, string $reason = null): TaskLease {
        $lease->released_at = now();
        $lease->status = 'RELEASED'; // or remain ACTIVE but released_at is set
        $lease->release_reason = $reason;
        $lease->save();
        
        // Remove from task
        $task = Task::find($lease->task_id);
        if ($task && $task->active_lease_id === $lease->id) {
            $task->active_lease_id = null;
            $task->lease_expires_at = null;
            $task->save();
        }
        
        return $lease;
    }
}
