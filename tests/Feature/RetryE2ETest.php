<?php
use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Run;
use App\Models\Task;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Livewire\Runs\Create;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use App\Collectors\CollectorInterface;
use App\Services\CapabilityRegistry;

//uses(RefreshDatabase::class);

class FlakyCollector implements CollectorInterface {
    public function collect($task): array {
        $attempts = DB::table('task_attempts')->where('task_id', $task->id)->count();
        if ($attempts === 1) {
            throw new \Exception("Flaky network error");
        }
        return [
            [
                'platform' => 'FACEBOOK',
                'entity_type' => 'POST',
                'stable_source_id' => '12345_TEST',
                'normalized_url' => 'https://facebook.com/12345_TEST',
                'payload' => [
                    'entity_type' => 'POST',
                    'text_content' => 'Recovered data'
                ]
            ]
        ];
    }
}

class TerminalCollector implements CollectorInterface {
    public function collect($task): array {
        throw new \Exception("Permanent failure");
    }
}

test('Retry Pipeline Success on Second Attempt', function () {
    DB::table('roles')->insertOrIgnore([['id' => 'owner', 'description' => 'Owner', 'is_internal_role' => false]]);
    $user = User::create(['id' => Str::uuid(), 'email' => 'retry@example.com', 'password_hash' => Hash::make('password123'), 'status' => 'ACTIVE']);
    $org = Organization::create(['id' => Str::uuid(), 'name' => 'Run Org', 'status' => 'ACTIVE']);
    OrganizationMembership::create(['id' => Str::uuid(), 'user_id' => $user->id, 'organization_id' => $org->id, 'role_id' => 'owner']);
    DB::table('credit_lots')->insert(['id' => Str::uuid(), 'organization_id' => $org->id, 'original_quantity' => 100.0, 'remaining_quantity' => 100.0, 'source' => 'TOP_UP', 'expires_at' => now()->addYear()]);
    
    app()->bind(\App\Collectors\FacebookPostsCollector::class, function () {
        return new FlakyCollector();
    });
    
    $this->actingAs($user);
    $this->withSession(['organization_id' => $org->id]);
    $this->withHeader('X-Organization-Id', $org->id);
    
    Livewire::test(Create::class)
        ->set('capability', 'facebook_posts')
        ->set('target_url', 'https://facebook.com/retry')
        ->set('max_pages', 2)
        ->call('submit')->assertHasNoErrors()->assertSet('error', '');
        
    $task = Task::first(); if (!$task) dd('TASK IS NULL');
    
    // Attempt 1
    Artisan::call('queue:work', ['--once' => true]);
    $task->refresh();
    $this->assertEquals('QUEUED', $task->status);
    $this->assertEquals(1, $task->attempt_count);
    
    // Attempt 2
    Artisan::call('queue:work', ['--once' => true]);
    $task->refresh();
    $this->assertEquals('COMPLETED', $task->status);
    $this->assertEquals(2, $task->attempt_count);
});

test('Terminal Exhaustion and DLQ', function () {
    DB::table('roles')->insertOrIgnore([['id' => 'owner', 'description' => 'Owner', 'is_internal_role' => false]]);
    $user = User::create(['id' => Str::uuid(), 'email' => 'terminal@example.com', 'password_hash' => Hash::make('password123'), 'status' => 'ACTIVE']);
    $org = Organization::create(['id' => Str::uuid(), 'name' => 'Run Org 2', 'status' => 'ACTIVE']);
    OrganizationMembership::create(['id' => Str::uuid(), 'user_id' => $user->id, 'organization_id' => $org->id, 'role_id' => 'owner']);
    DB::table('credit_lots')->insert(['id' => Str::uuid(), 'organization_id' => $org->id, 'original_quantity' => 100.0, 'remaining_quantity' => 100.0, 'source' => 'TOP_UP', 'expires_at' => now()->addYear()]);
    
    app()->bind(\App\Collectors\FacebookPostsCollector::class, function () {
        return new TerminalCollector();
    });
    
    $this->actingAs($user);
    $this->withSession(['organization_id' => $org->id]);
    $this->withHeader('X-Organization-Id', $org->id);
    
    Livewire::test(Create::class)
        ->set('capability', 'facebook_posts')
        ->set('target_url', 'https://facebook.com/terminal')
        ->set('max_pages', 2)
        ->call('submit')->assertHasNoErrors()->assertSet('error', '');
        
    $task = Task::first(); if (!$task) dd('TASK IS NULL');
    
    // Attempt 1, 2, 3
    Artisan::call('queue:work', ['--once' => true]);
    Artisan::call('queue:work', ['--once' => true]);
    Artisan::call('queue:work', ['--once' => true]);
    
    $task->refresh();
    $this->assertEquals('FAILED', $task->status);
    $this->assertEquals(3, $task->attempt_count);
    
    $dlqCount = DB::table('failed_jobs')->count();
    $this->assertEquals(1, $dlqCount);
});
