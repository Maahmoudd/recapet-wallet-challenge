<?php

namespace App\Actions\Wallet;

use App\Models\User;
use App\Repositories\Contract\IActivityLogRepository;
use App\Repositories\Contract\ILedgerEntryRepository;
use App\Repositories\Contract\ITransactionRepository;
use App\Repositories\Contract\IWalletRepository;
use Illuminate\Support\Facades\DB;

class DepositAction
{
    public function __construct(
        private readonly ITransactionRepository $transactionRepository,
        private readonly IWalletRepository $walletRepository,
        private readonly ILedgerEntryRepository $ledgerEntryRepository,
        private readonly IActivityLogRepository $activityLogRepository
    ) {}

    public function execute(User $user, array $data): array
    {
        if ($this->transactionRepository->existsByIdempotencyKey($data['idempotency_key'])) {
            $existingTransaction = $this->transactionRepository->findByIdempotencyKey($data['idempotency_key']);

            $this->activityLogRepository->log('duplicate_request_blocked', $existingTransaction, [
                'metadata' => ['idempotency_key' => $data['idempotency_key']]
            ], request(), $user);

            throw new \Exception('Duplicate transaction detected');
        }

        return DB::transaction(function () use ($user, $data) {
            $wallet = $this->walletRepository->getWithLock($user->wallet->id);

            if (!$wallet->isActive()) {
                throw new \Exception('Wallet is not active');
            }

            $transaction = $this->transactionRepository->create([
                'idempotency_key' => $data['idempotency_key'],
                'to_wallet_id' => $wallet->id,
                'type' => 'deposit',
                'amount' => $data['amount'],
                'fee_amount' => '0.00',
                'status' => 'pending',
                'metadata' => [
                    'source' => 'external_deposit',
                    'user_id' => $user->id,
                    'timestamp' => now()->toISOString(),
                ],
            ]);

            $newBalance = bcadd($wallet->balance, $data['amount'], 2);

            $this->walletRepository->updateBalanceWithLock($wallet->id, $newBalance);

            $this->ledgerEntryRepository->create([
                'wallet_id' => $wallet->id,
                'transaction_id' => $transaction->id,
                'type' => 'credit',
                'amount' => $data['amount'],
                'balance_after' => $newBalance,
                'description' => "Deposit of \${$data['amount']} to wallet",
            ]);

            $this->transactionRepository->updateStatus($transaction, 'completed');

            $this->activityLogRepository->log('wallet_deposit', $transaction, [
                'metadata' => [
                    'amount' => $data['amount'],
                    'previous_balance' => $wallet->balance,
                    'new_balance' => $newBalance,
                    'wallet_id' => $wallet->id,
                    'transaction_id' => $transaction->id,
                ]
            ], request(), $user);

            $transaction = $transaction->fresh(['toWallet.user']);

            return [
                'transaction' => $transaction,
                'new_balance' => $newBalance,
                'previous_balance' => $wallet->balance,
            ];
        });
    }
}
