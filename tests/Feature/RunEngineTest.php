<?php
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Organization;
use App\Models\RunRequest;
use App\Services\RunEngineService;

beforeEach(function () {
    Artisan::call('migrate:raw-down');
    Artisan::call('migrate:raw');
});

test('RunEngine creation', function() {
    $o1 = Organization::create(['id' => Str::uuid(), 'name' => 'O1', 'status' => 'ACTIVE']);
    
    $service = new RunEngineService();
    $run = $service->createRun($o1->id, 'SCRAPER_X', ['target_url' => 'http://test.com']);
    
    $this->assertNotNull($run);
    $this->assertEquals('QUEUED', $run->status);
    $this->assertEquals($o1->id, $run->organization_id);
    
    $req = RunRequest::find($run->id);
    $this->assertEquals('http://test.com', $req->target_url);
    
    $service->cancelRun($run);
    $this->assertEquals('CANCELLED', $run->status);
    
    $service->finalizeRun($run, 0, 10, 0, 0);
    $this->assertEquals('COMPLETED', $run->status);
});

test('RunEngine invalid capabilities and transitions', function() {
    $o1 = Organization::create(['id' => Str::uuid(), 'name' => 'O1', 'status' => 'ACTIVE']);
    $service = new RunEngineService();
    
    $this->expectException(\Exception::class);
    $service->createRun($o1->id, 'INVALID', []);
});
