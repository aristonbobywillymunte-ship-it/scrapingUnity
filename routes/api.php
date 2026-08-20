<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/v1/auth/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/v1/auth/logout', [AuthController::class, 'logout']);
    Route::post('/v1/auth/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/v1/auth/me', [AuthController::class, 'me']);
});
