<?php

namespace App\Actions\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class HealthCheckAction
{
    public function execute(): array
    {
        $startTime = microtime(true);

        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
            'wallet_service' => $this->checkWalletService(),
            'transaction_service' => $this->checkTransactionService(),
        ];

        $overallStatus = $this->determineOverallStatus($checks);
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'status' => $overallStatus,
            'timestamp' => now()->toISOString(),
            'response_time_ms' => $responseTime,
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'services' => $checks,
            'system_info' => $this->getSystemInfo(),
        ];
    }

    private function checkDatabase(): array
    {
        try {
            $startTime = microtime(true);

            DB::connection()->getPdo();

            $result = DB::select('SELECT 1 as test');

            DB::transaction(function () {
                DB::select('SELECT 1');
            });

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'connection' => 'active',
                'driver' => DB::connection()->getDriverName(),
                'message' => 'Database connection successful'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Database connection failed'
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $startTime = microtime(true);
            $testKey = 'health_check_' . time();
            $testValue = 'test_' . uniqid();

            Cache::put($testKey, $testValue, 60);

            $retrieved = Cache::get($testKey);

            Cache::forget($testKey);

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($retrieved === $testValue) {
                return [
                    'status' => 'healthy',
                    'response_time_ms' => $responseTime,
                    'driver' => config('cache.default'),
                    'message' => 'Cache operations successful'
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Cache read/write test failed'
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Cache system failed'
            ];
        }
    }

    private function checkStorage(): array
    {
        try {
            $startTime = microtime(true);
            $testFile = 'health_check_' . time() . '.txt';
            $testContent = 'health check test';

            Storage::put($testFile, $testContent);

            $retrieved = Storage::get($testFile);

            Storage::delete($testFile);

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($retrieved === $testContent) {
                return [
                    'status' => 'healthy',
                    'response_time_ms' => $responseTime,
                    'driver' => config('filesystems.default'),
                    'message' => 'Storage operations successful'
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Storage read/write test failed'
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Storage system failed'
            ];
        }
    }

    private function checkWalletService(): array
    {
        try {
            $startTime = microtime(true);

            $activeWallets = DB::table('wallets')
                ->where('status', 'active')
                ->count();

            $totalWallets = DB::table('wallets')->count();

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'active_wallets' => $activeWallets,
                'total_wallets' => $totalWallets,
                'message' => 'Wallet service operational'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Wallet service failed'
            ];
        }
    }

    private function checkTransactionService(): array
    {
        try {
            $startTime = microtime(true);

            $recentTransactions = DB::table('transactions')
                ->where('created_at', '>=', now()->subHours(24))
                ->count();

            $pendingTransactions = DB::table('transactions')
                ->where('status', 'pending')
                ->count();

            $failedTransactions = DB::table('transactions')
                ->where('status', 'failed')
                ->where('created_at', '>=', now()->subHours(1))
                ->count();

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            $status = 'healthy';
            $message = 'Transaction service operational';

            if ($failedTransactions > 10) {
                $status = 'degraded';
                $message = 'High number of recent failed transactions detected';
            }

            if ($pendingTransactions > 100) {
                $status = 'degraded';
                $message = 'High number of pending transactions detected';
            }

            return [
                'status' => $status,
                'response_time_ms' => $responseTime,
                'recent_transactions_24h' => $recentTransactions,
                'pending_transactions' => $pendingTransactions,
                'failed_transactions_1h' => $failedTransactions,
                'message' => $message
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Transaction service failed'
            ];
        }
    }

    private function determineOverallStatus(array $checks): string
    {
        $statuses = collect($checks)->pluck('status');

        if ($statuses->contains('unhealthy')) {
            return 'unhealthy';
        }

        if ($statuses->contains('degraded')) {
            return 'degraded';
        }

        return 'healthy';
    }

    private function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'timezone' => config('app.timezone'),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
            'uptime' => $this->getApplicationUptime(),
        ];
    }

    private function getApplicationUptime(): string
    {
        try {
            $startTime = Cache::get('app_start_time');
            if (!$startTime) {
                $startTime = now();
                Cache::put('app_start_time', $startTime, now()->addWeek());
            }

            return $startTime->diffForHumans(now(), true);
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }
}
