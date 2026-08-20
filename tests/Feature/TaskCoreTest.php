<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use App\Models\Organization;
use App\Models\Run;
use App\Models\Task;
use App\Services\TaskEngineService;
use App\Services\TaskTransitionService;

beforeEach(function () {
    Artisan::call('migrate:raw-down');
    Artisan::call('migrate:raw');
});

test('Task Core operations', function () {
    $service = new TaskEngineService(new TaskTransitionService());
    $o1 = Organization::create(['id' => Str::uuid(), 'name' => 'O1']);
    $o2 = Organization::create(['id' => Str::uuid(), 'name' => 'O2']);

    $run = Run::create([
        'id' => Str::uuid(),
        'organization_id' => $o1->id,
        'capability' => 'facebook_posts',
        'status' => 'QUEUED'
    ]);

    // 1. valid Task create, 2. initial QUEUED, 3. org matches
    $task = $service->createTask($run->id, $o1->id, 'facebook_posts');
    expect($task->id)->not->toBeNull();
    expect($task->status)->toBe('QUEUED');
    expect($task->organization_id)->toBe($o1->id);

    // 4. cross-org Task creation rejected
    try {
        $service->createTask($run->id, $o2->id, 'facebook_comments');
        $this->fail('Should reject cross-org creation');
    } catch (\Exception $e) {
        expect($e->getMessage())->toContain('Run not found or organization mismatch');
    }

    // 5. duplicate logical Task rejected
    try {
        $service->createTask($run->id, $o1->id, 'facebook_posts');
        $this->fail('Should reject duplicate task');
    } catch (\Exception $e) {
        expect($e->getMessage())->toContain('Duplicate task');
    }

    // 6. Task under COMPLETED Run rejected
    $run->status = 'COMPLETED';
    $run->save();
    try {
        $service->createTask($run->id, $o1->id, 'instagram_posts');
        $this->fail('Should reject terminal run');
    } catch (\Exception $e) {
        expect($e->getMessage())->toContain('terminal run');
    }

    // 9, 10, 11 transitions
    $run->status = 'RUNNING';
    $run->save();
    $task2 = $service->createTask($run->id, $o1->id, 'instagram_posts');
    
    $service->markLeased($task2, 'worker1');
    expect($task2->status)->toBe('LEASED');
    
    $service->startTask($task2);
    expect($task2->status)->toBe('RUNNING');
    
    $service->completeTask($task2);
    expect($task2->status)->toBe('COMPLETED');
    
    // 14, 15 invalid and terminal transitions
    try {
        $service->startTask($task2); // COMPLETED -> RUNNING
        $this->fail('Should reject terminal resurrection');
    } catch (\Exception $e) {
        expect($e->getMessage())->toContain('Invalid task transition');
    }
    
    // 16. own org read
    $readTask = $service->getTaskForOrganization($o1->id, $task2->id);
    expect($readTask->id)->toBe($task2->id);
    
    // 17. cross org read denied
    try {
        $service->getTaskForOrganization($o2->id, $task2->id);
        $this->fail('Should reject cross-org read');
    } catch (\Exception $e) {
        expect($e->getMessage())->toContain('Task not found or organization mismatch');
    }
});
