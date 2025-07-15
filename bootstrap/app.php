<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

            if ($e instanceof \Illuminate\Validation\ValidationException) return null;

            if ($e instanceof ThrottleRequestsException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests',
                ], 429);
            }

            if ($e instanceof \Illuminate\Auth\AuthenticationException) return null;
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) return null;
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) return null;

            $exceptionMap = [
                'Duplicate transaction detected' => [409, 'Duplicate transaction detected', null],
                'Insufficient balance' => [400, 'Insufficient balance for operation', ['error_code' => 'INSUFFICIENT_BALANCE']],
                'Wallet is not active' => [403, 'Wallet is not active', ['error_code' => 'WALLET_INACTIVE']],
                'Sender wallet is not active' => [403, 'Your wallet is not active', ['error_code' => 'SENDER_WALLET_INACTIVE']],
                'Recipient wallet is not active' => [403, 'Recipient wallet is not active', ['error_code' => 'RECIPIENT_WALLET_INACTIVE']],

                'Recipient not found' => [404, 'Recipient user not found', ['error_code' => 'RECIPIENT_NOT_FOUND']],
                'Cannot transfer to yourself' => [400, 'Self-transfer is not allowed', ['error_code' => 'SELF_TRANSFER_NOT_ALLOWED']],

                'User not found' => [404, 'User not found', null],
                'Invalid credentials' => [401, 'Invalid credentials', null],
                'Access denied' => [403, 'Access denied', null],

                'Resource not found' => [404, 'Resource not found', null],
                'Rate limit exceeded' => [429, 'Too many requests', null],
            ];

            if (!array_key_exists($e->getMessage(), $exceptionMap)) return null;

            Log::error('API operation failed', [
                'user_id' => auth()->id(),
                'endpoint' => $request->path(),
                'error' => $e->getMessage(),
            ]);

            [$status, $message, $data] = $exceptionMap[$e->getMessage()];

            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => $data,
            ], $status);
        });
    })->create();
