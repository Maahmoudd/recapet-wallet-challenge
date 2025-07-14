<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contract\ITransactionRepository;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;

class TransactionRepository extends BaseRepository implements ITransactionRepository
{
    public function __construct(Transaction $model)
    {
        parent::__construct($model);
    }


    public function findByIdempotencyKey(string $key): ?Transaction
    {
        return $this->findFirstBy('idempotency_key', $key);
    }


    public function existsByIdempotencyKey(string $key): bool
    {
        return $this->existsBy('idempotency_key', $key);
    }


    public function getTransactionsForWallet(int $walletId, array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = $this->model->with(['fromWallet.user', 'toWallet.user'])
            ->where(function ($q) use ($walletId) {
                $q->where('from_wallet_id', $walletId)
                    ->orWhere('to_wallet_id', $walletId);
            });

        // Apply filters
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
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


    public function getPendingTransactions(): Collection
    {
        return $this->model->where('status', 'pending')->get();
    }


    public function updateStatus(Transaction $transaction, string $status): bool
    {
        return $this->update($transaction, ['status' => $status]);
    }


    public function getByTypeAndStatus(string $type, string $status): Collection
    {
        return $this->model->where('type', $type)
            ->where('status', $status)
            ->get();
    }
}
