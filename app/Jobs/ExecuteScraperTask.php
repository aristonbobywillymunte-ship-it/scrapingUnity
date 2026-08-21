<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\WorkerExecutionPipeline;

class ExecuteScraperTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $taskId;

    public function __construct($taskId)
    {
        $this->taskId = $taskId;
    }

    public function handle(WorkerExecutionPipeline $pipeline) {
        $pipeline->execute($this->taskId);
    }
}
