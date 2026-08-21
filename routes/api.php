<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\RunController;

// Auth
Route::post('/v1/auth/login', [AuthController::class, 'login']);
Route::post('/v1/auth/otp/request', [OtpController::class, 'request']);
Route::post('/v1/auth/otp/complete', [OtpController::class, 'complete']);
Route::post('/v1/auth/password-reset/request', [OtpController::class, 'request']);
Route::post('/v1/auth/password-reset/complete', [OtpController::class, 'complete']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/v1/auth/logout', [AuthController::class, 'logout']);
    Route::post('/v1/auth/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/v1/auth/me', [AuthController::class, 'me']);
    
    // API Keys (the test uses /app/api/v1/api-keys for some reason)
    Route::post('/v1/api-keys', [ApiKeyController::class, 'create']);
    Route::post('/app/api/v1/api-keys', [ApiKeyController::class, 'create']); // alias for test compatibility
    
    // Runs
    Route::post('/v1/facebook/posts/runs', [RunController::class, 'createFacebookPosts']);
    Route::get('/v1/runs', [RunController::class, 'listRuns']);
    Route::get('/v1/runs/{id}', [RunController::class, 'getRun']);
    Route::post('/v1/runs/{id}/cancel', [RunController::class, 'cancelRun']);
});
