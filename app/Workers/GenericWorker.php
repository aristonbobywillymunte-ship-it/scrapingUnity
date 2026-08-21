<?php
namespace App\Workers;

class GenericWorker implements WorkerInterface {
    public function run($collector, $task): array {
        return $collector->collect($task);
    }
}
