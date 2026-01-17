<?php

namespace App\Domain\Events\Models;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Events\Enums\EventBookingStatus;
use App\Domain\Payments\Models\PaymentOrder;
use App\Domain\Venues\Models\Venue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class EventBooking extends Model
{
    use HasFactory;
    use Auditable;

    protected $fillable = [
        'event_id',
        'venue_id',
        'starts_at',
        'ends_at',
        'status',
        'payment_order_id',
        'payment_order_snapshot',
        'moderation_comment',
        'moderated_by',
        'moderated_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'moderated_at' => 'datetime',
            'payment_order_snapshot' => 'array',
            'status' => EventBookingStatus::class,
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class);
    }

    public function payment(): MorphOne
    {
        return $this->morphOne(\App\Domain\Payments\Models\Payment::class, 'payable');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }
}
