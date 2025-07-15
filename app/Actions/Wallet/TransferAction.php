<?php

namespace App\Actions\Wallet;

use App\Models\User;
use App\Repositories\Contract\ITransactionRepository;
use App\Repositories\Contract\IUserRepository;
use App\Repositories\Contract\IWalletRepository;
use Illuminate\Support\Facades\DB;

class TransferAction
{
    public function __construct(
        private IUserRepository $userRepository,
        private IWalletRepository $walletRepository,
        private ITransactionRepository $transactionRepository
    ) {}

    public function execute(User $sender, array $data): array
    {
        return DB::transaction(function () use ($sender, $data) {
            $existingTransaction = $this->transactionRepository->findByIdempotencyKey(
                $data['idempotency_key']
            );

            if ($existingTransaction) {
                throw new \Exception('Duplicate transaction detected');
            }

            $recipient = $this->userRepository->findByEmail($data['recipient_email']);
            if (!$recipient) {
                throw new \Exception('Recipient not found');
            }

            if ($sender->id === $recipient->id) {
                throw new \Exception('Cannot transfer to yourself');
            }

            $senderWallet = $this->walletRepository->findByUserIdForUpdate($sender->id);
            $recipientWallet = $this->walletRepository->findByUserIdForUpdate($recipient->id);

            if (!$senderWallet || !$senderWallet->isActive()) {
                throw new \Exception('Sender wallet is not active');
            }

            if (!$recipientWallet || !$recipientWallet->isActive()) {
                throw new \Exception('Recipient wallet is not active');
            }

            $amount = $data['amount'];
            $fee = $this->calculateFee($amount);
            $totalDeduction = $amount + $fee;

            if ($senderWallet->balance < $totalDeduction) {
                throw new \Exception('Insufficient balance');
            }

            $transferTransaction = $this->transactionRepository->create([
                'idempotency_key' => $data['idempotency_key'],
                'from_wallet_id' => $senderWallet->id,
                'to_wallet_id' => $recipientWallet->id,
                'type' => 'transfer',
                'amount' => $amount,
                'fee_amount' => $fee,
                'status' => 'completed',
                'metadata' => [
                    'transfer_type' => 'p2p',
                    'sender_id' => $sender->id,
                    'sender_email' => $sender->email,
                    'recipient_id' => $recipient->id,
                    'recipient_email' => $recipient->email,
                    'description' => $data['description'] ?? "P2P Transfer",
                    'sender_balance_before' => $senderWallet->balance,
                    'sender_balance_after' => $senderWallet->balance - $totalDeduction,
                    'recipient_balance_before' => $recipientWallet->balance,
                    'recipient_balance_after' => $recipientWallet->balance + $amount,
                    'fee_calculation' => [
                        'base_fee' => $fee > 0 ? 2.50 : 0,
                        'percentage_fee' => $fee > 0 ? ($amount * 0.10) : 0,
                        'total_fee' => $fee,
                        'fee_applied' => $fee > 0,
                    ]
                ]
            ]);

            $this->walletRepository->updateBalanceWithLock(
                $senderWallet->id,
                $senderWallet->balance - $totalDeduction
            );

            $this->walletRepository->updateBalanceWithLock(
                $recipientWallet->id,
                $recipientWallet->balance + $amount
            );

            return [
                'transaction' => $transferTransaction->fresh(),
                'sender_wallet_id' => $senderWallet->id,
                'recipient_wallet_id' => $recipientWallet->id,
                'sender_new_balance' => $senderWallet->balance - $totalDeduction,
                'recipient_new_balance' => $recipientWallet->balance + $amount,
                'transfer_amount' => $amount,
                'fee_amount' => $fee,
                'total_deducted' => $totalDeduction,
                'reference' => $data['idempotency_key'],
            ];
        });
    }

    private function calculateFee(float $amount): float
    {
        if ($amount <= config('wallet.calculation.min_transfer_amount')) {
            return 0;
        }

        $baseFee = config('wallet.calculation.base_fee');
        $percentageFee = $amount * config('wallet.calculation.percentage_fee');

        return round($baseFee + $percentageFee, 2);
    }
}
