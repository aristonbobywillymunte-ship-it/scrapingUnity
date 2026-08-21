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

uses(RefreshDatabase::class);

test('E2E Real Run Pipeline with DB Queue', function () {
    DB::table('roles')->insertOrIgnore([
        ['id' => 'owner', 'description' => 'Owner', 'is_internal_role' => false]
    ]);

    $user = User::create([
        'id' => Str::uuid(),
        'email' => 'run_e2e@example.com',
        'password_hash' => Hash::make('password123'),
        'status' => 'ACTIVE'
    ]);
    
    $org = Organization::create([
        'id' => Str::uuid(),
        'name' => 'Run Org',
        'status' => 'ACTIVE'
    ]);
    
    OrganizationMembership::create([
        'id' => Str::uuid(),
        'user_id' => $user->id,
        'organization_id' => $org->id,
        'role_id' => 'owner'
    ]);
    
    // Give credits
    DB::table('credit_lots')->insert([
        'id' => Str::uuid(),
        'organization_id' => $org->id,
        'original_quantity' => 100.0,
        'remaining_quantity' => 100.0,
        'source' => 'TOP_UP',
        'expires_at' => now()->addYear()
    ]);
    
    $this->actingAs($user);
    $this->withSession(['organization_id' => $org->id]);
    $this->withHeader('X-Organization-Id', $org->id);
    
    Livewire::test(Create::class)
        ->set('capability', 'facebook_posts')
        ->set('target_url', 'https://facebook.com/zuck')
        ->set('max_pages', 2)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('error', '');
        
    $run = Run::where('organization_id', $org->id)->first();
    $this->assertNotNull($run);
    
    $task = Task::where('run_id', $run->id)->first();
    $this->assertNotNull($task);
    $this->assertEquals('QUEUED', $task->status);
    
    // Check job is in the DB
    $jobsCount = DB::table('jobs')->count();
    $this->assertEquals(1, $jobsCount);
    
    // Run the worker synchronously in test
    Artisan::call('queue:work', ['--once' => true]);
    
    // Check job disappeared
    $this->assertEquals(0, DB::table('jobs')->count());
    
    $task->refresh();
    $run->refresh();
    
    $this->assertEquals('COMPLETED', $task->status);
    $this->assertEquals('COMPLETED', $run->status);
    
    $result = DB::table('canonical_posts')
        ->join('run_results', 'canonical_posts.canonical_entity_id', '=', 'run_results.canonical_entity_id')
        ->where('run_results.run_id', $run->id)
        ->first();
    $this->assertNotNull($result);
    $this->assertStringContainsString('Actual execution data', $result->text_content);
});
