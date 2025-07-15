<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contract\ITransactionRepository;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TransactionRepository extends BaseRepository implements ITransactionRepository
{
    public function __construct(Transaction $model)
    {
        parent::__construct($model);
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?Transaction
    {
        return Transaction::where('idempotency_key', $idempotencyKey)
            ->first();
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

    public function getWalletTransactionsPaginated(int $walletId, array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = $this->model->with(['fromWallet.user', 'toWallet.user'])
            ->where(function ($q) use ($walletId) {
                $q->where('from_wallet_id', $walletId)
                    ->orWhere('to_wallet_id', $walletId);
            });

        $query = $this->applyFilters($query, $filters);

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        $perPage = $filters['per_page'] ?? 20;

        return $query->paginate($perPage, ['*'], 'page', $filters['page'] ?? 1);
    }

    public function getTransactionSummary(int $walletId, array $filters = []): array
    {
        $baseQuery = $this->model->where(function ($q) use ($walletId) {
            $q->where('from_wallet_id', $walletId)
                ->orWhere('to_wallet_id', $walletId);
        });

        $baseQuery = $this->applyFilters($baseQuery, $filters);

        $totalCount = (clone $baseQuery)->count();

        $statusCounts = (clone $baseQuery)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $typeCounts = (clone $baseQuery)
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        $incomingSum = (clone $baseQuery)
            ->where('to_wallet_id', $walletId)
            ->where('status', 'completed')
            ->sum('amount');

        $outgoingSum = (clone $baseQuery)
            ->where('from_wallet_id', $walletId)
            ->where('status', 'completed')
            ->sum(DB::raw('amount + fee_amount'));

        $totalFeesCharged = (clone $baseQuery)
            ->where('from_wallet_id', $walletId)
            ->where('status', 'completed')
            ->sum('fee_amount');

        return [
            'total_transactions' => $totalCount,
            'status_breakdown' => [
                'completed' => $statusCounts['completed'] ?? 0,
                'pending' => $statusCounts['pending'] ?? 0,
                'failed' => $statusCounts['failed'] ?? 0,
            ],
            'type_breakdown' => [
                'deposits' => $typeCounts['deposit'] ?? 0,
                'withdrawals' => $typeCounts['withdrawal'] ?? 0,
                'transfers' => $typeCounts['transfer'] ?? 0,
            ],
            'financial_summary' => [
                'total_received' => round($incomingSum, 2),
                'total_sent' => round($outgoingSum, 2),
                'total_fees_paid' => round($totalFeesCharged, 2),
                'net_amount' => round($incomingSum - $outgoingSum, 2),
            ]
        ];
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

    private function applyFilters($query, array $filters = [])
    {
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Support both date formats for compatibility
        if (isset($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        } elseif (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        } elseif (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }
}
