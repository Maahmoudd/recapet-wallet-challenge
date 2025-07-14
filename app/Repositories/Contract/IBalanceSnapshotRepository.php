<?php

namespace App\Repositories\Contract;

use App\Models\BalanceSnapshot;
use DateTime;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface IBalanceSnapshotRepository extends IBaseRepository
{
    public function createSnapshotForWallet(int $walletId, string $balance, DateTime $date = null): BalanceSnapshot;
    public function getSnapshotsForWallet(int $walletId, array $filters = []): LengthAwarePaginator;
    public function getLatestSnapshotForWallet(int $walletId): ?BalanceSnapshot;
    public function getSnapshotForDate(int $walletId, string $date): ?BalanceSnapshot;
    public function createDailySnapshots(DateTime $date = null): int;
    public function cleanOldSnapshots(int $daysToKeep = 365): int;
}
