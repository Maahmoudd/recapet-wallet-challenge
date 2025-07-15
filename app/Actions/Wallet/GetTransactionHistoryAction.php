<?php

namespace App\Actions\Wallet;

use App\Models\User;
use App\Repositories\Contract\ITransactionRepository;
use App\Repositories\Contract\IWalletRepository;

class GetTransactionHistoryAction
{
    public function __construct(
        private ITransactionRepository $transactionRepository,
        private IWalletRepository $walletRepository
    ) {}

    public function execute(User $user, array $filters): array
    {
        $wallet = $this->walletRepository->findByUserId($user->id);

        if (!$wallet) {
            throw new \Exception('Wallet not found');
        }

        $transactions = $this->transactionRepository->getWalletTransactionsPaginated(
            $wallet->id,
            $filters
        );

        $transformedTransactions = $transactions->getCollection()->map(function ($transaction) use ($wallet) {
            $this->addTransactionAttributes($transaction, $wallet);
            return $transaction;
        });

        $transactions->setCollection($transformedTransactions);

        $summary = $this->transactionRepository->getTransactionSummary($wallet->id, $filters);

        return [
            'transactions' => $transactions,
            'summary' => $summary,
            'wallet_balance' => $wallet->balance,
        ];
    }

    private function addTransactionAttributes($transaction, $wallet): void
    {
        $isOutgoing = $transaction->from_wallet_id === $wallet->id;
        $isIncoming = $transaction->to_wallet_id === $wallet->id;

        if ($transaction->type === 'transfer') {
            if ($isOutgoing && $isIncoming) {
                $direction = 'self';
                $displayAmount = $transaction->amount;
                $description = 'Self Transfer';
            } elseif ($isOutgoing) {
                $direction = 'outgoing';
                $displayAmount = -($transaction->amount + $transaction->fee_amount);
                $description = 'Transfer Sent';
            } else {
                $direction = 'incoming';
                $displayAmount = $transaction->amount;
                $description = 'Transfer Received';
            }
        } elseif ($transaction->type === 'deposit') {
            $direction = 'incoming';
            $displayAmount = $transaction->amount;
            $description = 'Deposit';
        } else { // withdrawal
            $direction = 'outgoing';
            $displayAmount = -($transaction->amount + $transaction->fee_amount);
            $description = 'Withdrawal';
        }

        $metadata = $transaction->metadata ?? [];
        $counterparty = null;

        if ($transaction->type === 'transfer') {
            if ($isOutgoing) {
                $counterparty = [
                    'type' => 'recipient',
                    'email' => $metadata['recipient_email'] ?? 'Unknown',
                    'id' => $metadata['recipient_id'] ?? null,
                ];
            } else {
                $counterparty = [
                    'type' => 'sender',
                    'email' => $metadata['sender_email'] ?? 'Unknown',
                    'id' => $metadata['sender_id'] ?? null,
                ];
            }
        }

        $transaction->direction = $direction;
        $transaction->display_amount = $displayAmount;
        $transaction->computed_description = $description;
        $transaction->counterparty = $counterparty;
    }

}
