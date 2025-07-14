<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'balance',
        'snapshot_date',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'snapshot_date' => 'datetime',
        'wallet_id' => 'integer',
    ];

    const UPDATED_AT = null;


    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }


    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('snapshot_date', $date);
    }


    public function scopeForWallet($query, int $walletId)
    {
        return $query->where('wallet_id', $walletId);
    }


    public function scopeBetweenDates($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('snapshot_date', [$startDate, $endDate]);
    }
}
