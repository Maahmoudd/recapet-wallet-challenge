<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'idempotency_key',
        'from_wallet_id',
        'to_wallet_id',
        'type',
        'amount',
        'fee_amount',
        'status',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'metadata' => 'array',
        'from_wallet_id' => 'integer',
        'to_wallet_id' => 'integer',
    ];


    public function fromWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'from_wallet_id');
    }


    public function toWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'to_wallet_id');
    }


    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }


    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }


    public function isPending(): bool
    {
        return $this->status === 'pending';
    }


    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }


    public function getTotalAmountAttribute(): string
    {
        return bcadd($this->amount, $this->fee_amount, 2);
    }


    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }


    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
