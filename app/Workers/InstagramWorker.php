<?php
namespace App\Workers;

class InstagramWorker implements WorkerInterface {
    public function run($collector, $task): array {
        return $collector->collect($task);
    }
}
