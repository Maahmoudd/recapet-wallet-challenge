<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contract\IWalletRepository;
use App\Models\Wallet;

class WalletRepository extends BaseRepository implements IWalletRepository
{
    public function findByUserId(int $userId): ?Wallet
    {
        return $this->findFirstBy('user_id', $userId);
    }

    public function findByUserIdForUpdate(int $userId): ?Wallet
    {
        return Wallet::where('user_id', $userId)->lockForUpdate()->first();
    }

    public function findByUserIdOrFail(int $userId): Wallet
    {
        return Wallet::where('user_id', $userId)->firstOrFail();
    }

    public function updateBalanceWithLock(int $walletId, string $newBalance): bool
    {
        return Wallet::where('id', $walletId)
            ->lockForUpdate()
            ->update(['balance' => $newBalance]);
    }

    public function getWithLock(int $walletId): Wallet
    {
        return Wallet::lockForUpdate()->findOrFail($walletId);
    }
}
