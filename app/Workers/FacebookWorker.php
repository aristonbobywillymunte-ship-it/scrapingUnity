<?php
namespace App\Workers;

class FacebookWorker implements WorkerInterface {
    public function run($collector, $task): array {
        // Normally this sets up a session, proxies, browser contexts, etc.
        return $collector->collect($task);
    }
}
