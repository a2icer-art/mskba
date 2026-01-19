<?php

namespace App\Domain\Venues\Services;

use App\Domain\Events\Enums\EventBookingStatus;
use App\Domain\Events\Models\EventBooking;
use App\Domain\Venues\Models\Venue;
use App\Domain\Venues\Models\VenueSchedule;
use App\Domain\Venues\Models\VenueScheduleException;
use App\Domain\Venues\Models\VenueScheduleExceptionInterval;
use App\Domain\Venues\Models\VenueScheduleInterval;
use Carbon\CarbonInterface;

class VenueAboutDataBuilder
{
    public function build(Venue $venue, string $typeSlug): array
    {
        $schedule = VenueSchedule::query()
            ->where('venue_id', $venue->id)
            ->with(['intervals', 'exceptions.intervals'])
            ->first();

        $weeklyIntervals = [];
        $exceptionsByDate = [];

        if ($schedule) {
            $weeklyIntervals = $schedule->intervals
                ->groupBy('day_of_week')
                ->map(function ($items) {
                    return $items
                        ->sortBy('starts_at')
                        ->map(fn (VenueScheduleInterval $interval) => [
                            'starts_at' => $this->formatTime($interval->starts_at),
                            'ends_at' => $this->formatTime($interval->ends_at),
                        ])
                        ->values()
                        ->all();
                })
                ->all();

            $exceptionsByDate = $schedule->exceptions
                ->keyBy(fn (VenueScheduleException $exception) => $exception->date?->toDateString() ?: '')
                ->all();
        }

        $startDate = now()->startOfDay();
        $endDate = (clone $startDate)->addDays(13)->endOfDay();

        $bookings = EventBooking::query()
            ->where('venue_id', $venue->id)
            ->whereIn('status', [
                EventBookingStatus::Pending->value,
                EventBookingStatus::AwaitingPayment->value,
                EventBookingStatus::Paid->value,
                EventBookingStatus::Approved->value,
            ])
            ->whereBetween('starts_at', [$startDate, $endDate])
            ->orderBy('starts_at')
            ->get(['starts_at', 'ends_at', 'status']);

        $bookingsByDate = [];
        foreach ($bookings as $booking) {
            $startsAt = $booking->starts_at;
            $endsAt = $booking->ends_at;
            if (!$startsAt || !$endsAt) {
                continue;
            }
            $dateKey = $startsAt->toDateString();
            $bookingsByDate[$dateKey][] = [
                'starts_at' => $startsAt->format('H:i'),
                'ends_at' => $endsAt->format('H:i'),
                'status' => $booking->status?->value,
            ];
        }

        $scheduleDays = [];
        for ($i = 0; $i < 14; $i++) {
            $date = (clone $startDate)->addDays($i);
            $dateKey = $date->toDateString();
            $exception = $exceptionsByDate[$dateKey] ?? null;
            $intervals = [];
            $isClosed = false;
            $isClosedByException = false;
            $comment = null;

            if ($exception) {
                $isClosed = (bool) $exception->is_closed;
                $isClosedByException = $isClosed;
                $comment = $exception->comment;
                if (!$isClosed) {
                    $intervals = $exception->intervals
                        ->sortBy('starts_at')
                        ->map(fn (VenueScheduleExceptionInterval $interval) => [
                            'starts_at' => $this->formatTime($interval->starts_at),
                            'ends_at' => $this->formatTime($interval->ends_at),
                        ])
                        ->values()
                        ->all();
                    $isClosed = $intervals === [];
                }
            } else {
                $intervals = $weeklyIntervals[$date->dayOfWeekIso] ?? [];
                $isClosed = $intervals === [];
            }

            $scheduleDays[] = [
                'date' => $dateKey,
                'day_of_week' => $date->dayOfWeekIso,
                'is_today' => $date->isToday(),
                'is_closed' => $isClosed,
                'is_closed_by_exception' => $isClosedByException,
                'comment' => $comment,
                'intervals' => $intervals,
                'bookings' => $bookingsByDate[$dateKey] ?? [],
            ];
        }

        return [
            'rating' => 5,
            'rating_count' => 12,
            'schedule_days' => $scheduleDays,
            'schedule_url' => "/venues/{$typeSlug}/{$venue->alias}/schedule",
            'feed_url' => "/venues/{$typeSlug}/{$venue->alias}/feed",
            'booking_url' => "/events?venue={$venue->alias}",
            'map_api_key' => config('integrations.yandex.api_key'),
        ];
    }

    private function formatTime($time): string
    {
        if (!$time) {
            return '';
        }

        if ($time instanceof CarbonInterface) {
            return $time->format('H:i');
        }

        return substr($time, 0, 5);
    }
}
