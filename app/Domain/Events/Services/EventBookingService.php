<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventBooking;
use App\Domain\Venues\Models\Venue;
use App\Domain\Venues\Models\VenueScheduleInterval;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class EventBookingService
{
    public function create(Event $event, Venue $venue, int $createdBy, CarbonInterface $startsAt, CarbonInterface $endsAt): EventBooking
    {
        $this->ensureBookingValid($event, $venue, $startsAt, $endsAt);

        return EventBooking::query()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'pending',
            'created_by' => $createdBy,
        ]);
    }

    private function ensureBookingValid(Event $event, Venue $venue, CarbonInterface $startsAt, CarbonInterface $endsAt): void
    {
        if ($startsAt->greaterThanOrEqualTo($endsAt)) {
            throw ValidationException::withMessages([
                'starts_at' => 'Время начала должно быть меньше времени окончания.',
            ]);
        }

        $minStart = now()->addMinutes(15);
        if ($startsAt->lt($minStart)) {
            throw ValidationException::withMessages([
                'starts_at' => 'Бронирование должно начинаться не ранее чем ' . $minStart->format('d.m.Y H:i') . '.',
            ]);
        }

        if ($endsAt->lt($startsAt->copy()->addMinutes(15))) {
            throw ValidationException::withMessages([
                'ends_at' => 'Длительность бронирования должна быть не менее 15 минут.',
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
            ->whereIn('status', ['pending', 'approved'])
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
}
