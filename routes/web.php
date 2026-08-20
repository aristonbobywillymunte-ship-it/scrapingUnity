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
    
    Route::get('/admin', \App\Livewire\Admin\Index::class)->name('admin');
    Route::get('/admin/operations', \App\Livewire\Admin\Operations::class)->name('admin.operations');
});
