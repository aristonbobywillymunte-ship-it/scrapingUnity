<?php
namespace App\Workers;

interface WorkerInterface {
    public function run($collector, $task): array;
}
