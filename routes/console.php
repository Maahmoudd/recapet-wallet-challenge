<?php

use App\Actions\System\HealthCheckAction;
use App\Models\ActivityLog;
use App\Models\BalanceSnapshot;
use App\Models\Transaction;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Here we define all scheduled tasks for the wallet system including
| daily balance snapshots, cleanup tasks, and monitoring.
|
*/

// Daily Balance Snapshots - Run every day at 11:59 PM
Schedule::command('wallet:create-snapshots')
    ->dailyAt('23:59')
    ->timezone(config('app.timezone'))
    ->name('Daily Balance Snapshots')
    ->description('Create daily balance snapshots for all wallets')
    ->withoutOverlapping(10) // Prevent overlapping executions (10 min timeout)
    ->onSuccess(function () {
        Log::info('Daily balance snapshots completed successfully');
    })
    ->onFailure(function () {
        Log::error('Daily balance snapshots failed');
    });

// Weekly Balance Snapshots - Run every Sunday at midnight (for weekly reporting)
Schedule::command('wallet:create-snapshots')
    ->weeklyOn(0, '00:00')
    ->timezone(config('app.timezone'))
    ->name('Weekly Balance Snapshots')
    ->description('Create weekly balance snapshots for reporting')
    ->withoutOverlapping(15);

// Monthly Cleanup - Remove old snapshots (keep last 365 days)
Schedule::call(function () {
    $deleted = BalanceSnapshot::where('snapshot_date', '<', now()->subDays(365))->delete();
    Log::info("Cleaned {$deleted} old balance snapshots");
})
    ->monthlyOn(1, '02:00')
    ->name('Monthly Snapshot Cleanup')
    ->description('Remove old balance snapshots to save storage');

// Health Check Monitoring - Log system health every hour
Schedule::call(function () {
    try {
        $healthAction = app(HealthCheckAction::class);
        $health = $healthAction->execute();

        if ($health['status'] !== 'healthy') {
            Log::warning('System health check detected issues', $health);
        }
    } catch (Exception $e) {
        Log::error('Health check failed', ['error' => $e->getMessage()]);
    }
})
    ->hourly()
    ->name('Hourly Health Check')
    ->description('Monitor system health and log any issues');

// Transaction Monitoring - Check for stuck pending transactions
Schedule::call(function () {
    $stuckTransactions = Transaction::where('status', 'pending')
        ->where('created_at', '<', now()->subHours(2))
        ->count();

    if ($stuckTransactions > 0) {
        Log::warning("Found {$stuckTransactions} transactions stuck in pending status for over 2 hours");
    }
})
    ->everyFifteenMinutes()
    ->name('Transaction Health Monitor')
    ->description('Monitor for stuck transactions and system issues');

// Daily Activity Log Cleanup (optional - remove old logs to save space)
Schedule::call(function () {
    $daysToKeep = config('wallet.activity_logs.retention_days', 90);
    $deleted = ActivityLog::where('created_at', '<', now()->subDays($daysToKeep))->delete();

    if ($deleted > 0) {
        Log::info("Cleaned {$deleted} old activity logs (keeping last {$daysToKeep} days)");
    }
})
    ->dailyAt('01:00')
    ->name('Daily Activity Log Cleanup')
    ->description('Remove old activity logs to save storage space');

/*
|--------------------------------------------------------------------------
| Manual Commands for Testing
|--------------------------------------------------------------------------
|
| These are example commands you can run manually for testing:
|
| php artisan wallet:create-snapshots
| php artisan wallet:create-snapshots --date=2025-01-01
| php artisan wallet:create-snapshots --date=2025-01-01 --force
|
*/
