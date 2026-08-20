<?php
namespace App\Services;

class TaskTransitionService {
    private const VALID_TRANSITIONS = [
        'QUEUED' => ['LEASED', 'CANCELLED'],
        'LEASED' => ['RUNNING', 'FAILED', 'CANCELLED', 'QUEUED'], // QUEUED for lease recovery
        'RUNNING' => ['COMPLETED', 'FAILED', 'RETRY_WAIT', 'CANCELLED'],
        'RETRY_WAIT' => ['LEASED', 'CANCELLED'],
        'COMPLETED' => [],
        'FAILED' => [],
        'CANCELLED' => [],
    ];

    public function canTransition(string $from, string $to): bool {
        if ($from === $to) {
            return true;
        }
        $allowed = self::VALID_TRANSITIONS[$from] ?? [];
        return in_array($to, $allowed, true);
    }

    public function validateTransition(string $from, string $to): void {
        if (!$this->canTransition($from, $to)) {
            throw new \Exception("Invalid task transition from {$from} to {$to}");
        }
    }
}
