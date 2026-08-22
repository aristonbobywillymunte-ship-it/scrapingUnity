<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\RunController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\UsageController;

// Public Auth Endpoints
Route::post('/v1/auth/login', [AuthController::class, 'login']);
Route::post('/v1/auth/otp/request', [OtpController::class, 'request']);
Route::post('/v1/auth/otp/complete', [OtpController::class, 'complete']);
Route::post('/v1/auth/password-reset/request', [OtpController::class, 'request']);
Route::post('/v1/auth/password-reset/complete', [OtpController::class, 'complete']);

// Canonical External Scraping API (Protected by Bearer API Key Middleware)
Route::middleware([\App\Http\Middleware\ApiKeyMiddleware::class, \App\Http\Middleware\PlatformLimiterMiddleware::class])->group(function () {
    // Universal Jobs API (04_API_SPECIFICATION)
    Route::post('/v1/jobs', [JobController::class, 'create']);
    Route::get('/v1/jobs', [JobController::class, 'index']);
    Route::get('/v1/jobs/{id}', [JobController::class, 'show']);
    Route::get('/v1/jobs/{id}/items', [JobController::class, 'items']);
    Route::delete('/v1/jobs/{id}', [JobController::class, 'cancel']);

    // Canonical Platform Capabilities
    Route::get('/v1/platforms', [PlatformController::class, 'index']);
    Route::get('/v1/platforms/{platform}', [PlatformController::class, 'show']);

    // Canonical Usage & Quota
    Route::get('/v1/usage', [UsageController::class, 'index']);
    Route::get('/v1/me', [AuthController::class, 'me']);

    // Webhooks Management
    Route::get('/v1/webhooks', [WebhookController::class, 'index']);
    Route::post('/v1/webhooks', [WebhookController::class, 'create']);
    Route::get('/v1/webhooks/{id}', [WebhookController::class, 'show']);
    Route::delete('/v1/webhooks/{id}', [WebhookController::class, 'delete']);
});

// Dashboard Session / Sanctum Auth for UI and Key Provisioning
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/v1/auth/logout', [AuthController::class, 'logout']);
    Route::post('/v1/auth/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/v1/auth/me', [AuthController::class, 'me']);

    // API Keys Lifecycle Management
    Route::get('/v1/api-keys', [ApiKeyController::class, 'index']);
    Route::post('/v1/api-keys', [ApiKeyController::class, 'create']);
    Route::delete('/v1/api-keys/{id}', [ApiKeyController::class, 'revoke']);

    // Legacy Runs Routes (Backwards Compatibility Layer)
    Route::post('/v1/facebook/posts/runs', [RunController::class, 'createFacebookPosts']);
    Route::get('/v1/runs', [RunController::class, 'listRuns']);
    Route::get('/v1/runs/{id}', [RunController::class, 'getRun']);
    Route::post('/v1/runs/{id}/cancel', [RunController::class, 'cancelRun']);
});
