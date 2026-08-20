<?php
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Organization;
use App\Models\Run;
use App\Services\TaskEngineService;

beforeEach(function () {
    Artisan::call('migrate:raw-down');
    Artisan::call('migrate:raw');
});

test('TaskEngine basic operations', function() {
    $o1 = Organization::create(['id' => Str::uuid(), 'name' => 'O1', 'status' => 'ACTIVE']);
    $run = Run::create([
        'id' => Str::uuid(),
        'organization_id' => $o1->id,
        'capability' => 'facebook_posts',
        'status' => 'QUEUED'
    ]);
    
    $service = app(TaskEngineService::class);
    $task = $service->createTask($run->id, $o1->id, 'facebook_posts');
    
    $this->assertNotNull($task);
    $this->assertEquals('QUEUED', $task->status);
    
    $service->startTask($task);
    $this->assertEquals('RUNNING', $task->status);
    
    $service->completeTask($task);
    $this->assertEquals('COMPLETED', $task->status);
});

test('TaskEngine invalid transitions', function() {
    $o1 = Organization::create(['id' => Str::uuid(), 'name' => 'O1', 'status' => 'ACTIVE']);
    $run = Run::create([
        'id' => Str::uuid(),
        'organization_id' => $o1->id,
        'capability' => 'facebook_posts',
        'status' => 'QUEUED'
    ]);
    $service = app(TaskEngineService::class);
    $task = $service->createTask($run->id, $o1->id, 'facebook_posts');
    $service->startTask($task);
    $service->completeTask($task);
    
    $this->expectException(\Exception::class);
    $service->startTask($task); // COMPLETED -> RUNNING is invalid
});
