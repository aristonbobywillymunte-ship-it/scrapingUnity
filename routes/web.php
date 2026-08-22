<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::middleware('guest')->group(function () {
    Route::get('/', function () { return redirect('/login'); });
    Route::get('/login', \App\Livewire\Auth\Login::class)->name('login');
    Route::get('/password-recovery', \App\Livewire\Auth\PasswordRecovery::class)->name('password.recovery');
    Route::get('/password-reset', \App\Livewire\Auth\PasswordReset::class)->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    Route::get('/dashboard', \App\Livewire\Dashboard::class)->name('dashboard');
    
    Route::get('/runs', \App\Livewire\Runs\Index::class)->name('runs.index');
    Route::get('/runs/create', \App\Livewire\Runs\Create::class)->name('runs.create');
    Route::get('/runs/{run}', \App\Livewire\Runs\Show::class)->name('runs.show');
    
    Route::get('/results', \App\Livewire\Results\Index::class)->name('results.index');
    Route::get('/results/{result}', \App\Livewire\Results\Show::class)->name('results.show');
    
    Route::get('/billing', \App\Livewire\Billing\Index::class)->name('billing');
    Route::get('/api-keys', \App\Livewire\ApiKeys\Index::class)->name('api-keys');
    
    Route::get('/organization', \App\Livewire\Organization\Index::class)->name('organization');
    Route::get('/organization/team', \App\Livewire\Organization\Team::class)->name('organization.team');
    
    Route::get('/profile', \App\Livewire\Profile\Index::class)->name('profile');
    Route::get('/profile/security', \App\Livewire\Profile\Security::class)->name('profile.security');
    
    // PRD Section 18 Admin Routes
    Route::prefix('admin')->group(function () {
        Route::get('/', \App\Livewire\Admin\Dashboard::class)->name('admin');
        Route::get('/operations', \App\Livewire\Admin\Operations::class)->name('admin.operations');

        // Users & Plans
        Route::get('/users', \App\Livewire\Admin\Users\Index::class)->name('admin.users.index');
        Route::get('/plans', \App\Livewire\Admin\Plans\Index::class)->name('admin.plans.index');

        // Data Center
        Route::get('/data-center', \App\Livewire\Admin\DataCenter\Index::class)->name('admin.data-center.index');

        // Scraping & Lab
        Route::get('/jobs', \App\Livewire\Admin\Jobs\Index::class)->name('admin.jobs.index');
        Route::get('/test-history', \App\Livewire\Admin\Jobs\TestHistory::class)->name('admin.test-history');

        // Platforms
        Route::get('/platforms', \App\Livewire\Admin\Platforms\Index::class)->name('admin.platforms.index');
        Route::get('/platforms/health', \App\Livewire\Admin\Platforms\Health::class)->name('admin.platforms.health');

        // Parser
        Route::get('/parser', \App\Livewire\Admin\Parser\Index::class)->name('admin.parser.index');

        // Infrastructure
        Route::get('/proxies', \App\Livewire\Admin\Proxies\Index::class)->name('admin.proxies.index');
        Route::get('/workers', \App\Livewire\Admin\Infrastructure\Workers::class)->name('admin.workers.index');
        Route::get('/queues', \App\Livewire\Admin\Infrastructure\Queues::class)->name('admin.queues.index');

        // System
        Route::get('/providers', \App\Livewire\Admin\System\Providers::class)->name('admin.providers.index');
        Route::get('/logs', \App\Livewire\Admin\System\Logs::class)->name('admin.logs.index');
        Route::get('/audit-logs', \App\Livewire\Admin\System\AuditLogs::class)->name('admin.system.audit-logs');
        Route::get('/settings', \App\Livewire\Admin\System\Settings::class)->name('admin.settings.index');
    });
});
