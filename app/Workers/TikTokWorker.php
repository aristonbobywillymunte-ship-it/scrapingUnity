<?php
namespace App\Workers;

class TikTokWorker implements WorkerInterface {
    public function run($collector, $task): array {
        return $collector->collect($task);
    }
}
