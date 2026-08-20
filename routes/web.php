<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\RunController;

// Align the routes to match the test definitions and openapi
Route::prefix('api/v1/auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::prefix('app/api/v1/auth')->group(function () {
    Route::post('/password-reset/request', [OtpController::class, 'request']);
    Route::post('/password-reset/verify', [OtpController::class, 'verify']);
    Route::post('/password-reset/complete', [OtpController::class, 'complete']);
});

Route::middleware('auth')->group(function () {
    Route::post('/api/v1/auth/logout', [AuthController::class, 'logout']);
    Route::post('/api/v1/auth/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/api/v1/auth/me', [AuthController::class, 'me']);
    
    Route::middleware(\App\Http\Middleware\TenantMiddleware::class)->group(function() {
        Route::post('/app/api/v1/api-keys', [ApiKeyController::class, 'create']);
        Route::get('/api/v1/runs', [RunController::class, 'listRuns']);
        Route::get('/api/v1/runs/{run_id}', [RunController::class, 'getRun']);
        Route::post('/api/v1/runs/{run_id}/cancel', [RunController::class, 'cancelRun']);
        Route::post('/api/v1/facebook/posts/runs', [RunController::class, 'createFacebookPosts']);
    });
});
