<?php

namespace App\Domain\Balances\Models;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Balances\Enums\BalanceTransactionType;
use App\Domain\Payments\Enums\PaymentCurrency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceTransaction extends Model
{
    use HasFactory;
    use Auditable;

    protected $fillable = [
        'balance_id',
        'user_id',
        'type',
        'amount',
        'currency',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'type' => BalanceTransactionType::class,
            'currency' => PaymentCurrency::class,
            'amount' => 'integer',
            'meta' => 'array',
        ];
    }

    public function balance(): BelongsTo
    {
        return $this->belongsTo(Balance::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
