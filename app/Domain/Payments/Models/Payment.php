<?php

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Enums\PaymentCurrency;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'payable_type',
        'payable_id',
        'payment_order_id',
        'payment_order_snapshot',
        'payment_code',
        'amount_minor',
        'currency',
        'status',
        'meta',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'payment_order_snapshot' => 'array',
            'amount_minor' => 'integer',
            'currency' => PaymentCurrency::class,
            'status' => PaymentStatus::class,
            'meta' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            if ($payment->payment_code) {
                return;
            }

            $payment->payment_code = static::generateUniqueCode();
        });
    }

    private static function generateUniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (self::query()->where('payment_code', $code)->exists());

        return $code;
    }
}
