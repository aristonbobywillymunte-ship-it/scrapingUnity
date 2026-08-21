<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Jobs\ExecuteScraperTask;
use Exception;

class RunOrchestrationService {
    public function __construct(
        private RunPreflightService $preflight,
        private BillingService $billing,
        private RunEngineService $runEngine,
        private TaskEngineService $taskEngine
    ) {}

    public function submitRun(User $user, string $orgId, string $capability, array $payload) {
        $capConfig = CapabilityRegistry::get($capability);
        if (!$capConfig) {
            throw new Exception("Capability missing in registry");
        }

        return DB::transaction(function () use ($user, $orgId, $capability, $payload, $capConfig) {
            // 1. Preflight validation
            $this->preflight->validate($user, $orgId, $capability);
            
            // 2. Create Run FIRST so we have the ID for billing
            $run = $this->runEngine->createRun($orgId, $capability, $payload);
            
            // 3. Pricing & Reservation
            $cost = $capConfig['cost'];
            $reservationId = $this->billing->reserveCredits($orgId, $cost, $run->id);
            
            // 4. Create Task
            $task = $this->taskEngine->createTask($run->id, $orgId, $capability, $payload);
            
            // 5. Dispatch Queue Job
            dispatch(new ExecuteScraperTask($task->id));
            
            return $run;
        });
    }
}
