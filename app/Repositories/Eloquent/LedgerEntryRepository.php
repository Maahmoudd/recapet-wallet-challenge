<?php

namespace App\Repositories\Eloquent;

use App\Models\LedgerEntry;
use App\Repositories\Contract\ILedgerEntryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class LedgerEntryRepository extends BaseRepository implements ILedgerEntryRepository
{
    public function __construct(LedgerEntry $model)
    {
        parent::__construct($model);
    }


    public function getEntriesForWallet(int $walletId, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->with(['transaction'])
            ->where('wallet_id', $walletId);

        // Apply filters
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 25, ['*'], '', $filters['page'] ?? 1);
    }


    public function getEntriesForTransaction(int $transactionId): Collection
    {
        return $this->model->where('transaction_id', $transactionId)
            ->orderBy('created_at')
            ->get();
    }


    public function getLatestBalanceForWallet(int $walletId): ?string
    {
        $entry = $this->model->where('wallet_id', $walletId)
            ->orderBy('created_at', 'desc')
            ->first();

        return $entry?->balance_after;
    }


    public function createMultiple(array $entries): Collection
    {
        $createdEntries = collect();

        foreach ($entries as $entryData) {
            $createdEntries->push($this->create($entryData));
        }

        return $createdEntries;
    }


    public function calculateBalanceForWallet(int $walletId): string
    {
        $credits = $this->model->where('wallet_id', $walletId)
            ->where('type', 'credit')
            ->sum('amount');

        $debits = $this->model->where('wallet_id', $walletId)
            ->whereIn('type', ['debit', 'fee'])
            ->sum('amount');

        return bcsub($credits, $debits, 2);
    }
}
