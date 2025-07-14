<?php

namespace App\Repositories\Eloquent;

use App\Models\BalanceSnapshot;
use App\Models\Wallet;
use App\Repositories\Contract\IBalanceSnapshotRepository;
use DateTime;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BalanceSnapshotRepository extends BaseRepository implements IBalanceSnapshotRepository
{
    public function __construct(BalanceSnapshot $model)
    {
        parent::__construct($model);
    }


    public function createSnapshotForWallet(int $walletId, string $balance, DateTime $date = null): BalanceSnapshot
    {
        return $this->create([
            'wallet_id' => $walletId,
            'balance' => $balance,
            'snapshot_date' => $date ?? now(),
        ]);
    }


    public function getSnapshotsForWallet(int $walletId, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->where('wallet_id', $walletId);

        // Apply filters
        if (!empty($filters['date_from'])) {
            $query->whereDate('snapshot_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('snapshot_date', '<=', $filters['date_to']);
        }

        return $query->orderBy('snapshot_date', 'desc')
            ->paginate($filters['per_page'] ?? 25, ['*'], '', $filters['page'] ?? 1);
    }


    public function getLatestSnapshotForWallet(int $walletId): ?BalanceSnapshot
    {
        return $this->model->where('wallet_id', $walletId)
            ->orderBy('snapshot_date', 'desc')
            ->first();
    }


    public function getSnapshotForDate(int $walletId, string $date): ?BalanceSnapshot
    {
        return $this->model->where('wallet_id', $walletId)
            ->whereDate('snapshot_date', $date)
            ->first();
    }


    public function createDailySnapshots(DateTime $date = null): int
    {
        $date = $date ?? now();
        $wallets = Wallet::all();
        $created = 0;

        foreach ($wallets as $wallet) {
            if (!$this->getSnapshotForDate($wallet->id, $date->format('Y-m-d'))) {
                $this->createSnapshotForWallet($wallet->id, $wallet->balance, $date);
                $created++;
            }
        }

        return $created;
    }


    public function cleanOldSnapshots(int $daysToKeep = 365): int
    {
        return $this->model->where('snapshot_date', '<', now()->subDays($daysToKeep))
            ->delete();
    }
}
