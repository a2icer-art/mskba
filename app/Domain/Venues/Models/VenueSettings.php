<?php

namespace App\Domain\Venues\Models;

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

    protected $fillable = [
        'venue_id',
        'booking_lead_time_minutes',
        'booking_min_interval_minutes',
        'payment_order',
    ];

    protected function casts(): array
    {
        return [
            'booking_lead_time_minutes' => 'integer',
            'booking_min_interval_minutes' => 'integer',
            'payment_order' => VenuePaymentOrder::class,
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
