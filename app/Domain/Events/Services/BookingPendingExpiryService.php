<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Enums\EventBookingModerationSource;
use App\Domain\Events\Enums\EventBookingStatus;
use App\Domain\Events\Models\EventBooking;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Venues\Models\VenueSchedule;
use App\Domain\Venues\Models\VenueScheduleException;
use App\Domain\Venues\Models\VenueScheduleExceptionInterval;
use App\Domain\Venues\Models\VenueScheduleInterval;
use App\Domain\Venues\Models\VenueSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class BookingPendingExpiryService
{
    private const THROTTLE_SECONDS = 30;
    private const THROTTLE_KEY = 'booking_pending_expiry_throttle';
    private const BATCH_LIMIT = 50;
    private const WARNING_CACHE_PREFIX = 'booking_pending_warning_';

    public function runIfDue(): int
    {
        if (!Cache::add(self::THROTTLE_KEY, now()->timestamp, self::THROTTLE_SECONDS)) {
            return 0;
        }

        return $this->processPending();
    }

    private function processPending(): int
    {
        $now = now();

        $bookings = EventBooking::query()
            ->where('status', EventBookingStatus::Pending->value)
            ->with([
                'venue.schedule.intervals',
                'venue.schedule.exceptions.intervals',
                'venue.settings',
                'payment',
            ])
            ->limit(self::BATCH_LIMIT)
            ->get();

        $cancelled = 0;

        foreach ($bookings as $booking) {
            if ($this->shouldCancel($booking, $now)) {
                $this->cancelBooking($booking);
                $cancelled++;
                continue;
            }

            $this->maybeWarn($booking, $now);
        }

        return $cancelled;
    }

    private function shouldCancel(EventBooking $booking, Carbon $now): bool
    {
        return $this->shouldCancelByReview($booking, $now)
            || $this->shouldCancelByStartCutoff($booking, $now);
    }

    private function shouldCancelByReview(EventBooking $booking, Carbon $now): bool
    {
        $settings = $this->resolveSettings($booking);
        $limitMinutes = (int) $settings->pending_review_minutes;
        if ($limitMinutes <= 0) {
            return false;
        }

        $createdAt = $booking->created_at;
        if (!$createdAt) {
            return false;
        }

        $elapsed = $this->resolveWorkingMinutes($booking, $createdAt, $now);

        return $elapsed >= $limitMinutes;
    }

    private function shouldCancelByStartCutoff(EventBooking $booking, Carbon $now): bool
    {
        $settings = $this->resolveSettings($booking);
        $limitMinutes = (int) $settings->pending_before_start_minutes;
        if ($limitMinutes <= 0) {
            return false;
        }

        if (!$booking->starts_at) {
            return false;
        }

        $minutesToStart = $now->diffInMinutes($booking->starts_at, false);

        return $minutesToStart <= $limitMinutes;
    }

    private function maybeWarn(EventBooking $booking, Carbon $now): void
    {
        $settings = $this->resolveSettings($booking);
        $warningMinutes = (int) $settings->pending_warning_minutes;
        if ($warningMinutes <= 0) {
            return;
        }

        $minutesLeft = $this->resolveMinutesUntilCancel($booking, $now);
        if ($minutesLeft === null || $minutesLeft <= 0 || $minutesLeft > $warningMinutes) {
            return;
        }

        $cacheKey = self::WARNING_CACHE_PREFIX . $booking->id;
        if (!Cache::add($cacheKey, now()->timestamp, now()->addHours(6))) {
            return;
        }

        app(BookingNotificationService::class)->notifyPendingWarning($booking, $warningMinutes);
    }

    private function resolveMinutesUntilCancel(EventBooking $booking, Carbon $now): ?int
    {
        $settings = $this->resolveSettings($booking);
        $candidates = [];

        $reviewLimit = (int) $settings->pending_review_minutes;
        if ($reviewLimit > 0 && $booking->created_at) {
            $elapsed = $this->resolveWorkingMinutes($booking, $booking->created_at, $now);
            $candidates[] = $reviewLimit - $elapsed;
        }

        $startLimit = (int) $settings->pending_before_start_minutes;
        if ($startLimit > 0 && $booking->starts_at) {
            $minutesToStart = $now->diffInMinutes($booking->starts_at, false);
            $candidates[] = $minutesToStart - $startLimit;
        }

        if ($candidates === []) {
            return null;
        }

        return (int) min($candidates);
    }

    private function resolveWorkingMinutes(EventBooking $booking, Carbon $from, Carbon $to): int
    {
        $schedule = $booking->venue?->schedule;
        if (!$schedule) {
            return (int) $from->diffInMinutes($to);
        }

        return $this->workingMinutesBetween($schedule, $from, $to);
    }

    private function workingMinutesBetween(VenueSchedule $schedule, Carbon $from, Carbon $to): int
    {
        if ($to->lessThanOrEqualTo($from)) {
            return 0;
        }

        $total = 0;
        $cursor = $from->copy()->startOfDay();
        $endDay = $to->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($endDay)) {
            $intervals = $this->resolveIntervalsForDate($schedule, $cursor);

            foreach ($intervals as [$intervalStart, $intervalEnd]) {
                $rangeStart = $intervalStart->greaterThan($from) ? $intervalStart : $from;
                $rangeEnd = $intervalEnd->lessThan($to) ? $intervalEnd : $to;
                if ($rangeStart->lessThan($rangeEnd)) {
                    $total += $rangeStart->diffInMinutes($rangeEnd);
                }
            }

            $cursor->addDay();
        }

        return $total;
    }

    private function resolveIntervalsForDate(VenueSchedule $schedule, Carbon $date): array
    {
        $exception = $schedule->exceptions->first(function (VenueScheduleException $item) use ($date) {
            return $item->date?->toDateString() === $date->toDateString();
        });

        if ($exception) {
            if ($exception->is_closed) {
                return [];
            }

            return $exception->intervals
                ->map(fn (VenueScheduleExceptionInterval $interval) => [
                    $date->copy()->setTimeFromTimeString($interval->starts_at),
                    $date->copy()->setTimeFromTimeString($interval->ends_at),
                ])
                ->all();
        }

        $dayOfWeek = (int) $date->isoWeekday();

        return $schedule->intervals
            ->where('day_of_week', $dayOfWeek)
            ->map(fn (VenueScheduleInterval $interval) => [
                $date->copy()->setTimeFromTimeString($interval->starts_at),
                $date->copy()->setTimeFromTimeString($interval->ends_at),
            ])
            ->all();
    }

    private function cancelBooking(EventBooking $booking): void
    {
        $booking->update([
            'status' => EventBookingStatus::Cancelled,
            'moderation_comment' => 'Вышло время ожидания подтверждения заявки.',
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

        app(BookingNotificationService::class)->notifyStatus($booking, EventBookingStatus::Cancelled, null);
    }

    private function resolveSettings(EventBooking $booking): VenueSettings
    {
        return $booking->venue?->settings
            ?? new VenueSettings([
                'pending_review_minutes' => VenueSettings::DEFAULT_PENDING_REVIEW_MINUTES,
                'pending_before_start_minutes' => VenueSettings::DEFAULT_PENDING_BEFORE_START_MINUTES,
                'pending_warning_minutes' => VenueSettings::DEFAULT_PENDING_WARNING_MINUTES,
            ]);
    }
}
