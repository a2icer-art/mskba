<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Enums\EventBookingModerationSource;
use App\Domain\Events\Enums\EventBookingStatus;
use App\Domain\Events\Models\EventBooking;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use Illuminate\Support\Facades\Cache;

class BookingPaymentExpiryService
{
    private const THROTTLE_SECONDS = 300;
    private const THROTTLE_KEY = 'booking_payment_expiry_throttle';
    private const BATCH_LIMIT = 50;

    public function runIfDue(): int
    {
        if (!Cache::add(self::THROTTLE_KEY, now()->timestamp, self::THROTTLE_SECONDS)) {
            return 0;
        }

        return $this->cancelExpired();
    }

    public function cancelIfExpired(EventBooking $booking): bool
    {
        if ($booking->status !== EventBookingStatus::AwaitingPayment) {
            return false;
        }

        if (!$booking->payment_due_at || $booking->payment_due_at->gt(now())) {
            return false;
        }

        $this->cancelBooking($booking);

        return true;
    }

    private function cancelExpired(): int
    {
        $now = now();

        $bookings = EventBooking::query()
            ->where('status', EventBookingStatus::AwaitingPayment->value)
            ->whereNotNull('payment_due_at')
            ->where('payment_due_at', '<=', $now)
            ->limit(self::BATCH_LIMIT)
            ->get();

        foreach ($bookings as $booking) {
            $this->cancelBooking($booking);
        }

        return $bookings->count();
    }

    private function cancelBooking(EventBooking $booking): void
    {
        $booking->update([
            'status' => EventBookingStatus::Cancelled,
            'moderation_comment' => 'Вышел срок на оплату.',
            'moderation_source' => EventBookingModerationSource::Auto,
            'moderated_by' => null,
            'moderated_at' => now(),
        ]);

        Payment::query()
            ->where('payable_type', $booking->getMorphClass())
            ->where('payable_id', $booking->id)
            ->whereIn('status', [PaymentStatus::Created->value, PaymentStatus::Pending->value])
            ->update([
                'status' => PaymentStatus::Cancelled,
            ]);
    }
}
