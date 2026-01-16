<?php

namespace App\Domain\Balances\Models;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Balances\Enums\BalanceStatus;
use App\Domain\Payments\Enums\PaymentCurrency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Balance extends Model
{
    use HasFactory;
    use Auditable;

    protected $fillable = [
        'user_id',
        'available_amount',
        'held_amount',
        'currency',
        'status',
        'block_reason',
        'blocked_at',
    ];

    protected function casts(): array
    {
        return [
            'available_amount' => 'integer',
            'held_amount' => 'integer',
            'currency' => PaymentCurrency::class,
            'status' => BalanceStatus::class,
            'blocked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BalanceTransaction::class);
    }
}
