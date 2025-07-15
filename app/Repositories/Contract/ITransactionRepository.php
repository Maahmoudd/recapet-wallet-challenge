<?php

namespace App\Repositories\Contract;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;

interface ITransactionRepository extends IBaseRepository
{
    public function findByIdempotencyKey(string $idempotencyKey): ?Transaction;
    public function existsByIdempotencyKey(string $key): bool;
    public function getTransactionsForWallet(int $walletId, array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    public function getPendingTransactions(): Collection;
    public function updateStatus(Transaction $transaction, string $status): bool;
    public function getByTypeAndStatus(string $type, string $status): Collection;
}
