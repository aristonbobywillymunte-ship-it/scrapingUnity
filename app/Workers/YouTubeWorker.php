<?php
namespace App\Workers;

class YouTubeWorker implements WorkerInterface {
    public function run($collector, $task): array {
        return $collector->collect($task);
    }
}
