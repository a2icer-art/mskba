<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventBooking;
use App\Domain\Events\Enums\EventBookingModerationSource;
use App\Domain\Events\Enums\EventBookingStatus;
use App\Domain\Events\Services\BookingNotificationService;
use App\Models\User;
use App\Domain\Payments\Enums\PaymentCurrency;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Venues\Models\Venue;
use App\Domain\Venues\Models\VenueSettings;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class EventBookingService
{
    public function create(Event $event, Venue $venue, int $createdBy, CarbonInterface $startsAt, CarbonInterface $endsAt): EventBooking
    {
        $this->ensureBookingValid($event, $venue, $startsAt, $endsAt);

        $booking = EventBooking::query()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => EventBookingStatus::Pending,
            'moderation_source' => EventBookingModerationSource::Manual,
            'created_by' => $createdBy,
        ]);

        $this->createInitialPayment($booking, $venue, $createdBy);
        $actor = User::query()->find($createdBy);
        app(BookingNotificationService::class)->notifyStatus($booking, EventBookingStatus::Pending, $actor);

        return $booking;
    }

    private function ensureBookingValid(Event $event, Venue $venue, CarbonInterface $startsAt, CarbonInterface $endsAt): void
    {
        if ($startsAt->greaterThanOrEqualTo($endsAt)) {
            throw ValidationException::withMessages([
                'starts_at' => 'Время начала должно быть меньше времени окончания.',
            ]);
        }

        $settings = $this->resolveSettings($venue);
        $leadMinutes = (int) $settings->booking_lead_time_minutes;
        $minIntervalMinutes = (int) $settings->booking_min_interval_minutes;

        $minStart = now()->addMinutes($leadMinutes);
        if ($startsAt->lt($minStart)) {
            throw ValidationException::withMessages([
                'starts_at' => 'Бронирование должно начинаться не ранее чем ' . $minStart->format('d.m.Y H:i') . '.',
            ]);
        }

        if ($endsAt->lt($startsAt->copy()->addMinutes($minIntervalMinutes))) {
            throw ValidationException::withMessages([
                'ends_at' => 'Длительность бронирования должна быть не менее ' . $minIntervalMinutes . ' минут.',
            ]);
        }

        if ($startsAt->toDateString() !== $endsAt->toDateString()) {
            throw ValidationException::withMessages([
                'ends_at' => 'Бронирование должно быть в рамках одного дня.',
            ]);
        }

        if ($event->starts_at && $event->ends_at) {
            if ($startsAt->lt($event->starts_at) || $endsAt->gt($event->ends_at)) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Интервал бронирования должен быть в пределах события.',
                ]);
            }
        }

        $schedule = $venue->schedule()->with(['intervals', 'exceptions.intervals'])->first();
        if (!$schedule) {
            throw ValidationException::withMessages([
                'venue_id' => 'Для площадки не задано расписание.',
            ]);
        }

        $date = $startsAt->toDateString();
        $exception = $schedule->exceptions->first(function ($item) use ($date) {
            return $item->date?->toDateString() === $date;
        });

        if ($exception && $exception->is_closed) {
            throw ValidationException::withMessages([
                'starts_at' => 'Площадка закрыта на выбранную дату.',
            ]);
        }

        if ($exception && !$exception->is_closed) {
            $intervals = $exception->intervals;
        } else {
            $dayOfWeek = (int) $startsAt->isoWeekday();
            $intervals = $schedule->intervals->where('day_of_week', $dayOfWeek);
        }

        if ($intervals->isEmpty()) {
            throw ValidationException::withMessages([
                'starts_at' => 'Площадка закрыта в выбранный день.',
            ]);
        }

        $fitsInterval = $intervals->contains(function ($interval) use ($startsAt, $endsAt): bool {
            $startMinutes = $this->timeToMinutes($startsAt->format('H:i'));
            $endMinutes = $this->timeToMinutes($endsAt->format('H:i'));
            $intervalStart = $this->timeToMinutes($interval->starts_at);
            $intervalEnd = $this->timeToMinutes($interval->ends_at);

            return $startMinutes >= $intervalStart && $endMinutes <= $intervalEnd;
        });

        if (!$fitsInterval) {
            throw ValidationException::withMessages([
                'starts_at' => 'Интервал бронирования выходит за расписание площадки.',
            ]);
        }

        $overlapExists = EventBooking::query()
            ->where('venue_id', $venue->id)
            ->whereIn('status', [
                EventBookingStatus::Pending->value,
                EventBookingStatus::AwaitingPayment->value,
                EventBookingStatus::Paid->value,
                EventBookingStatus::Approved->value,
            ])
            ->where(function ($query) use ($startsAt, $endsAt): void {
                $query->where('starts_at', '<', $endsAt)
                    ->where('ends_at', '>', $startsAt);
            })
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'starts_at' => 'Выбранный интервал уже занят.',
            ]);
        }
    }

    private function timeToMinutes(string $time): int
    {
        $chunk = substr($time, 0, 5);
        [$hours, $minutes] = array_pad(explode(':', $chunk, 2), 2, 0);

        return ((int) $hours) * 60 + (int) $minutes;
    }

    private function resolveSettings(Venue $venue): VenueSettings
    {
        $settings = $venue->settings()->first();
        if ($settings) {
            return $settings;
        }

        return new VenueSettings([
            'booking_lead_time_minutes' => VenueSettings::DEFAULT_BOOKING_LEAD_MINUTES,
            'booking_min_interval_minutes' => VenueSettings::DEFAULT_BOOKING_MIN_INTERVAL_MINUTES,
            'payment_order' => VenueSettings::DEFAULT_PAYMENT_ORDER->value,
            'rental_duration_minutes' => VenueSettings::DEFAULT_RENTAL_DURATION_MINUTES,
            'rental_price_rub' => VenueSettings::DEFAULT_RENTAL_PRICE_RUB,
        ]);
    }

    private function createInitialPayment(EventBooking $booking, Venue $venue, int $createdBy): void
    {
        $settings = $this->resolveSettings($venue);
        $durationMinutes = $booking->starts_at && $booking->ends_at
            ? max(1, $booking->starts_at->diffInMinutes($booking->ends_at))
            : 0;
        $unitMinutes = max(1, (int) $settings->rental_duration_minutes);
        $units = $durationMinutes > 0 ? (int) ceil($durationMinutes / $unitMinutes) : 0;
        $unitPrice = (int) $settings->rental_price_rub;
        $amount = $units * $unitPrice;

        Payment::query()->create([
            'user_id' => $createdBy,
            'payable_type' => $booking->getMorphClass(),
            'payable_id' => $booking->id,
            'amount_minor' => $amount,
            'currency' => PaymentCurrency::Rub,
            'status' => PaymentStatus::Created,
            'meta' => [
                'duration_minutes' => $durationMinutes,
                'unit_minutes' => $unitMinutes,
                'unit_price_rub' => $unitPrice,
                'units' => $units,
            ],
        ]);
    }
}
