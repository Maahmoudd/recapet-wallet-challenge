<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BalanceSnapshotResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'balance' => $this->balance,
            'snapshot_date' => $this->snapshot_date?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'wallet' => new WalletResource($this->whenLoaded('wallet')),
        ];
    }
}
