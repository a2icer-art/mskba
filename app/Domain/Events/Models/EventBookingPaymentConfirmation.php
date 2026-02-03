<?php

namespace App\Domain\Events\Models;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Events\Enums\EventBookingPaymentConfirmationStatus;
use App\Domain\Media\Models\Media;
use App\Domain\Payments\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventBookingPaymentConfirmation extends Model
{
    use HasFactory;
    use Auditable;

    protected $table = 'booking_payment_confirmations';

    protected $fillable = [
        'event_booking_id',
        'payment_method_id',
        'payment_method_snapshot',
        'evidence_comment',
        'evidence_media_id',
        'status',
        'requested_by_user_id',
        'decided_by_user_id',
        'decided_at',
        'decision_comment',
    ];

    protected function casts(): array
    {
        return [
            'payment_method_snapshot' => 'array',
            'status' => EventBookingPaymentConfirmationStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(EventBooking::class, 'event_booking_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function evidenceMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'evidence_media_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
