<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'balance',
        'status',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'user_id' => 'integer',
    ];


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    public function sentTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'from_wallet_id');
    }


    public function receivedTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'to_wallet_id');
    }


    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }


    public function balanceSnapshots(): HasMany
    {
        return $this->hasMany(BalanceSnapshot::class);
    }


    public function isActive(): bool
    {
        return $this->status === 'active';
    }


    public function hasSufficientBalance(string $amount): bool
    {
        return bccomp($this->balance, $amount, 2) >= 0;
    }
}
