<?php
namespace App\Services;

use App\Models\Task;
use App\Models\TaskAttempt;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TaskAttemptService {
    public function beginAttempt(Task $task, string $workerIdentity): TaskAttempt {
        return DB::transaction(function () use ($task, $workerIdentity) {
            $lockedTask = Task::where('id', $task->id)->lockForUpdate()->first();
            
            $nextAttemptNumber = $lockedTask->attempt_count + 1;
            
            $attempt = TaskAttempt::create([
                'id' => Str::uuid(),
                'task_id' => $lockedTask->id,
                'run_id' => $lockedTask->run_id,
                'organization_id' => $lockedTask->organization_id,
                'attempt_number' => $nextAttemptNumber,
                'worker_identity' => $workerIdentity,
                'started_at' => now(),
            ]);
            
            $lockedTask->attempt_count = $nextAttemptNumber;
            $lockedTask->save();
            
            return $attempt;
        });
    }

    public function completeAttempt(TaskAttempt $attempt, array $safeMetadata = []): TaskAttempt {
        // Sanitize metadata logic here in real app
        $attempt->outcome = 'SUCCESS';
        $attempt->safe_diagnostics = json_encode($safeMetadata);
        $attempt->completed_at = now();
        $attempt->save();
        return $attempt;
    }

    public function failAttempt(TaskAttempt $attempt, string $errorCategory, string $errorCode, array $safeDiagnostics = []): TaskAttempt {
        $attempt->outcome = 'FAILED';
        $attempt->error_category = $errorCategory;
        $attempt->error_code = $errorCode;
        $attempt->safe_diagnostics = json_encode($safeDiagnostics);
        $attempt->completed_at = now();
        $attempt->save();
        return $attempt;
    }
}
