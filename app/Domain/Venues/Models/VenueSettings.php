<?php

namespace App\Domain\Venues\Models;

use App\Domain\Payments\Models\PaymentOrder;
use App\Domain\Venues\Enums\VenueBookingMode;
use App\Domain\Venues\Enums\VenuePaymentOrder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenueSettings extends Model
{
    use HasFactory;

    public const DEFAULT_BOOKING_LEAD_MINUTES = 15;
    public const DEFAULT_BOOKING_MIN_INTERVAL_MINUTES = 30;
    public const DEFAULT_PAYMENT_ORDER = VenuePaymentOrder::Prepayment;
    public const DEFAULT_RENTAL_DURATION_MINUTES = 60;
    public const DEFAULT_RENTAL_PRICE_RUB = 0;
    public const DEFAULT_BOOKING_MODE = VenueBookingMode::Instant;

    protected $fillable = [
        'venue_id',
        'booking_lead_time_minutes',
        'booking_min_interval_minutes',
        'payment_order_id',
        'rental_duration_minutes',
        'rental_price_rub',
        'booking_mode',
    ];

    protected function casts(): array
    {
        return [
            'booking_lead_time_minutes' => 'integer',
            'booking_min_interval_minutes' => 'integer',
            'payment_order_id' => 'integer',
            'rental_duration_minutes' => 'integer',
            'rental_price_rub' => 'integer',
            'booking_mode' => VenueBookingMode::class,
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class);
    }
}
