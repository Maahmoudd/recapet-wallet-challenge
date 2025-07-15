<?php

namespace App\Console\Commands;

use App\Repositories\Contract\IBalanceSnapshotRepository;
use App\Repositories\Contract\IActivityLogRepository;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CreateBalanceSnapshotsCommand extends Command
{
    protected $signature = 'wallet:create-snapshots
                            {--date= : Specific date for snapshots (Y-m-d format)}
                            {--force : Force creation even if snapshots exist for the date}';

    protected $description = 'Create daily balance snapshots for all wallets';

    public function __construct(
        private IBalanceSnapshotRepository $snapshotRepository,
        private IActivityLogRepository $activityLogRepository
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $startTime = microtime(true);

        $dateInput = $this->option('date');
        $snapshotDate = $dateInput ? Carbon::createFromFormat('Y-m-d', $dateInput) : now();
        $force = $this->option('force');

        $this->info("Creating balance snapshots for: {$snapshotDate->format('Y-m-d')}");

        try {
            if (!$force && $this->snapshotsExistForDate($snapshotDate)) {
                $this->warn("Snapshots already exist for {$snapshotDate->format('Y-m-d')}. Use --force to override.");
                return self::FAILURE;
            }

            $created = $this->snapshotRepository->createDailySnapshots($snapshotDate);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->logSnapshotActivity($created, $snapshotDate, $duration);

            $this->newLine();
            $this->info("✅ Balance snapshots created successfully!");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Date', $snapshotDate->format('Y-m-d')],
                    ['Snapshots Created', $created],
                    ['Duration', $duration . ' ms'],
                ]
            );

            if ($snapshotDate->isToday()) {
                $this->cleanOldSnapshots();
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to create balance snapshots: " . $e->getMessage());

            $this->activityLogRepository->log(
                'snapshot_creation_failed',
                new \stdClass(),
                [
                    'metadata' => [
                        'error' => $e->getMessage(),
                        'date' => $snapshotDate->format('Y-m-d'),
                        'trace' => $e->getTraceAsString(),
                    ]
                ],
                null,
                null
            );

            return self::FAILURE;
        }
    }

    private function snapshotsExistForDate(Carbon $date): bool
    {
        return \App\Models\BalanceSnapshot::whereDate('snapshot_date', $date->format('Y-m-d'))
            ->exists();
    }

    private function cleanOldSnapshots(): void
    {
        $this->info("Cleaning old snapshots...");

        $daysToKeep = config('wallet.snapshots.retention_days', 365);
        $deleted = $this->snapshotRepository->cleanOldSnapshots($daysToKeep);

        if ($deleted > 0) {
            $this->info("Cleaned {$deleted} old snapshots (keeping last {$daysToKeep} days)");
        }
    }

    private function logSnapshotActivity(int $created, Carbon $date, float $duration): void
    {
        $this->activityLogRepository->log(
            'snapshot_creation_completed',
            new \stdClass(),
            [
                'metadata' => [
                    'snapshots_created' => $created,
                    'snapshot_date' => $date->format('Y-m-d'),
                    'duration_ms' => $duration,
                    'execution_time' => now()->toISOString(),
                ]
            ],
            null,
            null
        );
    }
}
