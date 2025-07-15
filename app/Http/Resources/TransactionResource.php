<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'idempotency_key' => $this->idempotency_key,
            'type' => $this->type,
            'amount' => $this->amount,
            'fee_amount' => $this->fee_amount,
            'total_amount' => $this->total_amount,
            'status' => $this->status, // Requirement #4: Record status updates
            'status_description' => $this->getStatusDescription(),
            'description' => $this->getTransactionDescription(),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'from_wallet' => new WalletResource($this->whenLoaded('fromWallet')),
            'to_wallet' => new WalletResource($this->whenLoaded('toWallet')),
            'sender' => new UserResource($this->whenLoaded('fromWallet.user')),
            'recipient' => new UserResource($this->whenLoaded('toWallet.user')),
        ];
    }

    private function getTransactionDescription(): string
    {
        return match ($this->type) {
            'deposit' => 'Deposit to wallet',
            'withdrawal' => 'Withdrawal from wallet',
            'transfer' => $this->fromWallet && $this->toWallet
                ? "Transfer from {$this->fromWallet->user->email} to {$this->toWallet->user->email}"
                : 'Transfer between wallets',
            default => ucfirst($this->type),
        };
    }

    private function getStatusDescription(): string
    {
        return match ($this->status) {
            'pending' => 'Transaction is being processed',
            'completed' => 'Transaction completed successfully',
            'failed' => 'Transaction failed',
            default => ucfirst($this->status),
        };
    }
}
