<?php

namespace App\Actions\Wallet;

use App\Models\User;
use App\Repositories\Contract\ITransactionRepository;
use App\Repositories\Contract\IUserRepository;
use App\Repositories\Contract\IWalletRepository;
use App\Repositories\Contract\ILedgerEntryRepository;
use App\Repositories\Contract\IActivityLogRepository;
use Illuminate\Support\Facades\DB;

class TransferAction
{
    public function __construct(
        private IUserRepository $userRepository,
        private IWalletRepository $walletRepository,
        private ITransactionRepository $transactionRepository,
        private ILedgerEntryRepository $ledgerRepository,
        private IActivityLogRepository $activityLogRepository
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
                        'base_fee' => $fee > 0 ? config('wallet.calculation.base_fee') : 0,
                        'percentage_fee' => $fee > 0 ? ($amount * config('wallet.calculation.percentage_fee')) : 0,
                        'total_fee' => $fee,
                        'fee_applied' => $fee > 0,
                    ]
                ]
            ]);

            $this->createLedgerEntries($transferTransaction, $senderWallet, $recipientWallet, $amount, $fee);

            $this->walletRepository->updateBalanceWithLock(
                $senderWallet->id,
                $senderWallet->balance - $totalDeduction
            );

            $this->walletRepository->updateBalanceWithLock(
                $recipientWallet->id,
                $recipientWallet->balance + $amount
            );

            $this->logTransferActivity($transferTransaction, $sender, $recipient, $amount, $fee);

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
        if ($amount <= config('wallet.calculation.min_transfer_amount', 25.00)) {
            return 0;
        }

        $baseFee = config('wallet.calculation.base_fee', 2.50);
        $percentageFee = $amount * config('wallet.calculation.percentage_fee', 0.10);

        return round($baseFee + $percentageFee, 2);
    }

    private function createLedgerEntries($transaction, $senderWallet, $recipientWallet, $amount, $fee): void
    {
        $this->ledgerRepository->create([
            'transaction_id' => $transaction->id,
            'wallet_id' => $senderWallet->id,
            'type' => 'debit',
            'amount' => $amount + $fee,
            'balance_before' => $senderWallet->balance,
            'balance_after' => $senderWallet->balance - ($amount + $fee),
            'description' => "Transfer sent to {$transaction->metadata['recipient_email']}",
            'reference' => $transaction->idempotency_key,
            'metadata' => [
                'transaction_type' => 'transfer_out',
                'transfer_amount' => $amount,
                'fee_amount' => $fee,
                'recipient_wallet_id' => $recipientWallet->id,
            ]
        ]);

        $this->ledgerRepository->create([
            'transaction_id' => $transaction->id,
            'wallet_id' => $recipientWallet->id,
            'type' => 'credit',
            'amount' => $amount,
            'balance_before' => $recipientWallet->balance,
            'balance_after' => $recipientWallet->balance + $amount,
            'description' => "Transfer received from {$transaction->metadata['sender_email']}",
            'reference' => $transaction->idempotency_key,
            'metadata' => [
                'transaction_type' => 'transfer_in',
                'sender_wallet_id' => $senderWallet->id,
            ]
        ]);

        if ($fee > 0) {
            $this->ledgerRepository->create([
                'transaction_id' => $transaction->id,
                'wallet_id' => $senderWallet->id,
                'type' => 'fee',
                'amount' => $fee,
                'balance_before' => $senderWallet->balance - $amount,
                'balance_after' => $senderWallet->balance - ($amount + $fee),
                'description' => "Transfer fee for transaction {$transaction->idempotency_key}",
                'reference' => $transaction->idempotency_key . '_FEE',
                'metadata' => [
                    'transaction_type' => 'transfer_fee',
                    'base_fee' => config('wallet.calculation.base_fee', 2.50),
                    'percentage_fee' => $amount * config('wallet.calculation.percentage_fee', 0.10),
                    'original_transaction_id' => $transaction->id,
                ]
            ]);
        }
    }

    private function logTransferActivity($transaction, $sender, $recipient, $amount, $fee): void
    {
        // Log sender activity using the repository's log function
        $this->activityLogRepository->log(
            'wallet_transfer_sent',
            $transaction,
            [
                'metadata' => [
                    'transaction_id' => $transaction->id,
                    'idempotency_key' => $transaction->idempotency_key,
                    'recipient_id' => $recipient->id,
                    'recipient_email' => $recipient->email,
                    'amount' => $amount,
                    'fee' => $fee,
                    'total_deducted' => $amount + $fee,
                ]
            ],
            request(),
            $sender
        );

        // Log recipient activity using the repository's log function
        $this->activityLogRepository->log(
            'wallet_transfer_received',
            $transaction,
            [
                'metadata' => [
                    'transaction_id' => $transaction->id,
                    'idempotency_key' => $transaction->idempotency_key,
                    'sender_id' => $sender->id,
                    'sender_email' => $sender->email,
                    'amount' => $amount,
                ]
            ],
            request(),
            $recipient
        );

        // Log system activity for monitoring (no user context)
        $this->activityLogRepository->log(
            'transaction_completed',
            $transaction,
            [
                'metadata' => [
                    'transaction_id' => $transaction->id,
                    'idempotency_key' => $transaction->idempotency_key,
                    'sender_id' => $sender->id,
                    'recipient_id' => $recipient->id,
                    'transaction_type' => 'transfer',
                    'amount' => $amount,
                    'fee' => $fee,
                    'transfer_type' => 'p2p',
                    'processing_time_ms' => microtime(true) * 1000 - (request()->server('REQUEST_TIME_FLOAT') * 1000),
                ]
            ],
            request(),
            null // System log - no specific user
        );
    }
}
