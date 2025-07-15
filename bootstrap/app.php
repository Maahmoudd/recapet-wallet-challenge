<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (!$request->expectsJson()) return null;

            Log::error('API operation failed', [
                'user_id' => auth()->id(),
                'endpoint' => $request->path(),
                'error' => $e->getMessage(),
            ]);

            $exceptionMap = [
                'Duplicate transaction detected' => [409, 'Duplicate transaction detected'],
                'Insufficient balance' => [400, 'Insufficient balance for withdrawal', ['error_code' => 'INSUFFICIENT_BALANCE']],
                'Wallet is not active' => [403, 'Wallet is not active', ['error_code' => 'WALLET_INACTIVE']],
                'User not found' => [404, 'User not found'],
                'Invalid credentials' => [401, 'Invalid credentials'],
                'Access denied' => [403, 'Access denied'],
                'Resource not found' => [404, 'Resource not found'],
                'Validation failed' => [422, 'Validation failed'],
                'Rate limit exceeded' => [429, 'Too many requests'],
            ];

            [$status, $message, $data] = $exceptionMap[$e->getMessage()] ?? [500, 'Internal server error', null];

            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => $data,
            ], $status);
        });
    })->create();
