<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\HealthCheckController;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->prefix('wallet')->group(function () {
    Route::post('/deposit', [WalletController::class, 'deposit']);
    Route::post('/withdraw', [WalletController::class, 'withdraw']);
    Route::post('/transfer', [WalletController::class, 'transfer']);

    Route::get('/transactions', [WalletController::class, 'getTransactionHistory']);

});

// TODO: move to separate file and add admin guard
Route::prefix('health')->group(function () {
    Route::get('/healthz', [HealthCheckController::class, 'healthz']);
    Route::get('/ready', [HealthCheckController::class, 'readiness']);
    Route::get('/live', [HealthCheckController::class, 'liveness']);
});
