<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use App\Models\Organization;
use App\Models\Run;
use App\Models\Task;
use App\Models\TaskAttempt;
use App\Models\TaskLease;
use App\Services\TaskEngineService;
use App\Services\TaskTransitionService;
use App\Services\TaskAttemptService;
use App\Services\TaskLeaseService;

beforeEach(function () {
    Artisan::call('migrate:raw-down');
    Artisan::call('migrate:raw');
});

test('Task Attempts basic lifecycle', function () {
    $engine = new TaskEngineService(new TaskTransitionService());
    $attemptService = new TaskAttemptService();
    
    $o1 = Organization::create(['id' => Str::uuid(), 'name' => 'O1']);
    $run = Run::create(['id' => Str::uuid(), 'organization_id' => $o1->id, 'capability' => 'x_posts', 'status' => 'QUEUED']);
    $task = $engine->createTask($run->id, $o1->id, 'x_posts');
    
    $attempt1 = $attemptService->beginAttempt($task, 'worker_1');
    expect($attempt1->attempt_number)->toBe(1);
    
    $attemptService->completeAttempt($attempt1, ['success' => true]);
    $attempt1->refresh();
    expect($attempt1->outcome)->toBe('SUCCESS');
    
    $attempt2 = $attemptService->beginAttempt($task, 'worker_1');
    expect($attempt2->attempt_number)->toBe(2);
    
    $task->refresh();
    expect($task->attempt_count)->toBe(2);
});

test('Task Lease acquisition and heartbeat', function () {
    $engine = new TaskEngineService(new TaskTransitionService());
    $leaseService = new TaskLeaseService(new TaskTransitionService());
    
    $o1 = Organization::create(['id' => Str::uuid(), 'name' => 'O1']);
    $run = Run::create(['id' => Str::uuid(), 'organization_id' => $o1->id, 'capability' => 'youtube_videos', 'status' => 'QUEUED']);
    $task = $engine->createTask($run->id, $o1->id, 'youtube_videos');
    
    $lease = $leaseService->acquire($task, 'worker_x');
    expect($lease)->not->toBeNull();
    
    $task->refresh();
    expect($task->status)->toBe('LEASED');
    
    $leaseService->heartbeat($lease);
    expect($lease->heartbeat_at)->not->toBeNull();
    
    $leaseService->release($lease);
    
    $lease->refresh();
    expect($lease->released_at)->not->toBeNull();
});
