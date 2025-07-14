<?php

namespace App\Repositories\Contract;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ILedgerEntryRepository extends IBaseRepository
{
    public function getEntriesForWallet(int $walletId, array $filters = []): LengthAwarePaginator;
    public function getEntriesForTransaction(int $transactionId): Collection;
    public function getLatestBalanceForWallet(int $walletId): ?string;
    public function createMultiple(array $entries): Collection;
    public function calculateBalanceForWallet(int $walletId): string;
}
