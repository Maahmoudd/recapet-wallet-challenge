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

    /**
     * Find wallet by user ID or fail.
     */
    public function findByUserIdOrFail(int $userId): Wallet
    {
        return $this->model->where('user_id', $userId)->firstOrFail();
    }

    /**
     * Update wallet balance with locking.
     */
    public function updateBalanceWithLock(int $walletId, string $newBalance): bool
    {
        return $this->model
            ->where('id', $walletId)
            ->lockForUpdate()
            ->update(['balance' => $newBalance, 'updated_at' => now()]);
    }

    /**
     * Get wallet with lock for update.
     */
    public function getWithLock(int $walletId): Wallet
    {
        return $this->model->lockForUpdate()->findOrFail($walletId);
    }
}
