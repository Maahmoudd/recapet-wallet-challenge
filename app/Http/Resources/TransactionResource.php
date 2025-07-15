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
            'direction' => $this->direction ?? null,
            'amount' => number_format($this->amount, 2, '.', ''),
            'fee_amount' => number_format($this->fee_amount, 2, '.', ''),
            'display_amount' => isset($this->display_amount) ? number_format($this->display_amount, 2, '.', '') : null,
            'status' => $this->status,
            'description' => $this->computed_description ?? null,
            'counterparty' => $this->counterparty ?? null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'metadata' => $this->metadata ?? null,

            'from_wallet' => $this->whenLoaded('fromWallet', function () {
                return [
                    'id' => $this->fromWallet->id,
                    'user' => $this->whenLoaded('fromWallet.user', [
                        'id' => $this->fromWallet->user->id,
                        'email' => $this->fromWallet->user->email,
                    ])
                ];
            }),
            'to_wallet' => $this->whenLoaded('toWallet', function () {
                return [
                    'id' => $this->toWallet->id,
                    'user' => $this->whenLoaded('toWallet.user', [
                        'id' => $this->toWallet->user->id,
                        'email' => $this->toWallet->user->email,
                    ])
                ];
            }),
        ];
    }
}
