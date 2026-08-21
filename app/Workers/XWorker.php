<?php
namespace App\Workers;

class XWorker implements WorkerInterface {
    public function run($collector, $task): array {
        return $collector->collect($task);
    }
}
