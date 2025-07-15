<?php

namespace App\Repositories\Contract;

use App\Models\Wallet;

interface IWalletRepository extends IBaseRepository
{
    public function findByUserId(int $userId): ?Wallet;
    public function findByUserIdForUpdate(int $userId): ?Wallet;
    public function findByUserIdOrFail(int $userId): Wallet;

    public function updateBalanceWithLock(int $walletId, string $newBalance): bool;

    public function getWithLock(int $walletId): Wallet;
}
