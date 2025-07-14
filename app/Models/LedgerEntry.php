<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'transaction_id',
        'type',
        'amount',
        'balance_after',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'wallet_id' => 'integer',
        'transaction_id' => 'integer',
    ];

    const UPDATED_AT = null;


    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }


    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }


    public function isCredit(): bool
    {
        return $this->type === 'credit';
    }


    public function isDebit(): bool
    {
        return $this->type === 'debit';
    }


    public function isFee(): bool
    {
        return $this->type === 'fee';
    }


    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }


    public function scopeForWallet($query, int $walletId)
    {
        return $query->where('wallet_id', $walletId);
    }
}
