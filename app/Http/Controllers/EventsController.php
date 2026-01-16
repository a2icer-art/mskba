<?php

namespace App\Http\Controllers;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Services\EventBookingService;
use App\Domain\Admin\Services\EventDefaultsService;
use App\Domain\Permissions\Enums\PermissionCode;
use App\Domain\Permissions\Services\PermissionChecker;
use App\Domain\Venues\Models\Venue;
use App\Domain\Venues\Models\VenueSettings;
use App\Presentation\Breadcrumbs\EventBreadcrumbsPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EventsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $checker = app(PermissionChecker::class);

        $events = Event::query()
            ->with(['type', 'organizer', 'bookings'])
            ->orderByDesc('created_at')
            ->get()
            ->map(static function (Event $event): array {
                $hasApprovedBooking = $event->bookings->contains(static fn ($booking) => $booking->status === 'approved');
                $hasCancelledBooking = $event->bookings->contains(static fn ($booking) => $booking->status === 'cancelled');
                $hasPendingBooking = $event->bookings->contains(static fn ($booking) => $booking->status === 'pending');
                $hasAwaitingPaymentBooking = $event->bookings->contains(static fn ($booking) => $booking->status === 'awaiting_payment');
                $hasPaidBooking = $event->bookings->contains(static fn ($booking) => $booking->status === 'paid');
                return [
                    'id' => $event->id,
                    'title' => $event->title ?: 'Событие',
                    'status' => $event->status,
                    'starts_at' => $event->starts_at?->toDateTimeString(),
                    'ends_at' => $event->ends_at?->toDateTimeString(),
                    'has_approved_booking' => $hasApprovedBooking,
                    'has_cancelled_booking' => $hasCancelledBooking,
                    'has_pending_booking' => $hasPendingBooking,
                    'has_awaiting_payment_booking' => $hasAwaitingPaymentBooking,
                    'has_paid_booking' => $hasPaidBooking,
                    'type' => $event->type
                        ? [
                            'code' => $event->type->code,
                            'label' => $event->type->label,
                        ]
                        : null,
                    'organizer' => $event->organizer
                        ? [
                            'id' => $event->organizer->id,
                            'login' => $event->organizer->login,
                        ]
                        : null,
                ];
            })
            ->all();

        $eventTypes = EventType::query()
            ->orderBy('label')
            ->get(['id', 'code', 'label'])
            ->map(static fn (EventType $type) => [
                'id' => $type->id,
                'code' => $type->code,
                'label' => $type->label,
            ])
            ->all();

        $canCreate = $user && $checker->can($user, PermissionCode::EventCreate);
        $canBook = $user && $checker->can($user, PermissionCode::VenueBooking);
        $breadcrumbs = app(EventBreadcrumbsPresenter::class)->present()['data'];

        return Inertia::render('Events', [
            'appName' => config('app.name'),
            'events' => $events,
            'eventTypes' => $eventTypes,
            'canCreate' => $canCreate,
            'canBook' => $canBook,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $defaultsService = app(EventDefaultsService::class);
        $eventDefaults = $defaultsService->get();

        $data = $request->validate(
            [
                'event_type_id' => ['required', 'integer', 'exists:event_types,id'],
                'title' => ['nullable', 'string', 'max:255'],
                'starts_at' => ['required', 'date'],
                'ends_at' => ['required', 'date'],
                'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            ]
        );

        $timezone = config('app.timezone');
        $startsAt = Carbon::parse($data['starts_at'], $timezone);
        $endsAt = Carbon::parse($data['ends_at'], $timezone);

        $eventMinDuration = (int) ($eventDefaults['min_duration_minutes'] ?? 15);
        if ($endsAt->lt($startsAt->copy()->addMinutes($eventMinDuration))) {
            return back()->withErrors([
                'ends_at' => 'Длительность события должна быть не менее ' . $eventMinDuration . ' минут.',
            ]);
        }

        $type = EventType::query()->find($data['event_type_id']);
        if (!$type) {
            return back()->withErrors(['event_type_id' => 'Тип события не найден.']);
        }

        $title = $data['title'] ?: $type->label;
        $venue = null;
        if (!empty($data['venue_id'])) {
            $checker = app(PermissionChecker::class);
            if (!$checker->can($user, PermissionCode::VenueBooking)) {
                return back()->withErrors(['venue_id' => 'Недостаточно прав для бронирования площадки.']);
            }

            $gameTypeCodes = ['game', 'training', 'game_training'];
            if (!in_array($type->code, $gameTypeCodes, true)) {
                return back()->withErrors(['venue_id' => 'Площадка доступна только для игровых типов событий.']);
            }

            $venue = Venue::query()
                ->visibleFor($user)
                ->whereKey($data['venue_id'])
                ->first();

            if (!$venue) {
                return back()->withErrors(['venue_id' => 'Площадка не найдена.']);
            }
        }

        if ($venue) {
            $settings = $venue->settings()->first();
            $leadMinutes = $settings?->booking_lead_time_minutes ?? VenueSettings::DEFAULT_BOOKING_LEAD_MINUTES;
        } else {
            $leadMinutes = (int) ($eventDefaults['lead_time_minutes'] ?? VenueSettings::DEFAULT_BOOKING_LEAD_MINUTES);
        }
        $minStart = Carbon::now($timezone)->addMinutes($leadMinutes);
        if ($startsAt->lt($minStart)) {
            return back()->withErrors([
                'starts_at' => 'Событие должно начинаться не ранее чем ' . $minStart->format('d.m.Y H:i') . '.',
            ]);
        }

        try {
            $event = DB::transaction(function () use ($type, $user, $title, $startsAt, $endsAt, $venue): Event {
                $event = Event::query()->create([
                    'event_type_id' => $type->id,
                    'organizer_id' => $user->id,
                    'status' => 'draft',
                    'title' => $title,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'timezone' => 'UTC+3',
                ]);

                if ($venue) {
                    app(EventBookingService::class)->create($event, $venue, $user->id, $startsAt, $endsAt);
                }

                return $event;
            });
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        $notice = $venue
            ? 'Событие и бронирование созданы.'
            : 'Событие создано.';

        return redirect()->route('events.show', $event)->with('notice', $notice);
    }

    public function show(Request $request, Event $event)
    {
        $user = $request->user();
        $checker = app(PermissionChecker::class);

        $event->loadMissing(['type', 'organizer', 'bookings.venue', 'bookings.paymentOrder', 'bookings.payment']);

        $bookings = $event->bookings
            ->map(static function ($booking): array {
                $snapshot = is_array($booking->payment_order_snapshot) ? $booking->payment_order_snapshot : [];
                return [
                    'id' => $booking->id,
                    'status' => $booking->status,
                  'starts_at' => $booking->starts_at?->toDateTimeString(),
                  'ends_at' => $booking->ends_at?->toDateTimeString(),
                  'moderation_comment' => $booking->moderation_comment,
                  'payment_order' => $snapshot['label'] ?? $booking->paymentOrder?->label,
                  'payment_code' => $booking->payment?->payment_code,
                  'venue' => $booking->venue
                        ? [
                            'id' => $booking->venue->id,
                            'name' => $booking->venue->name,
                            'alias' => $booking->venue->alias,
                        ]
                        : null,
                ];
            })
            ->all();

        $timezone = config('app.timezone');
        $now = Carbon::now($timezone);
        $bookingDeadlinePassed = $event->starts_at
            ? $event->starts_at->copy()->subMinutes(VenueSettings::DEFAULT_BOOKING_LEAD_MINUTES)->lte($now)
            : false;
        $canBook = $user && $checker->can($user, PermissionCode::VenueBooking);
        $hasApprovedBooking = $event->bookings->contains(static fn ($booking) => $booking->status === 'approved');
        $canDelete = !$hasApprovedBooking && $this->canDelete($user, $event);
        $breadcrumbs = app(EventBreadcrumbsPresenter::class)->present([
            'event' => $event,
        ])['data'];

        return Inertia::render('EventShow', [
            'appName' => config('app.name'),
            'event' => [
                'id' => $event->id,
                'title' => $event->title ?: 'Событие',
                'status' => $event->status,
                'starts_at' => $event->starts_at?->toDateTimeString(),
                'ends_at' => $event->ends_at?->toDateTimeString(),
                'type' => $event->type
                    ? [
                        'code' => $event->type->code,
                        'label' => $event->type->label,
                    ]
                    : null,
                'organizer' => $event->organizer
                    ? [
                        'id' => $event->organizer->id,
                        'login' => $event->organizer->login,
                    ]
                    : null,
            ],
            'bookings' => $bookings,
            'canBook' => $canBook,
            'bookingDeadlinePassed' => $bookingDeadlinePassed,
            'canDelete' => $canDelete,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function storeBooking(Request $request, Event $event, EventBookingService $service)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $data = $request->validate(
            [
                'venue_id' => ['required', 'integer', 'exists:venues,id'],
                'starts_at' => ['required', 'date'],
                'ends_at' => ['required', 'date', 'after:starts_at'],
            ],
            [
                'ends_at.after' => 'Время окончания должно быть позже времени начала.',
            ]
        );

        $venue = Venue::query()->find($data['venue_id']);
        if (!$venue) {
            return back()->withErrors(['venue_id' => 'Площадка не найдена.']);
        }

        $settings = $venue->settings()->first();
        $leadMinutes = $settings?->booking_lead_time_minutes ?? VenueSettings::DEFAULT_BOOKING_LEAD_MINUTES;
        $timezone = config('app.timezone');
        $now = Carbon::now($timezone);
        if ($event->starts_at && $event->starts_at->copy()->subMinutes($leadMinutes)->lte($now)) {
            return back()->withErrors([
                'booking' => 'Бронирование возможно не позднее чем за ' . $leadMinutes . ' минут до начала события.',
            ]);
        }

        $service->create(
            $event,
            $venue,
            $user->id,
            Carbon::parse($data['starts_at']),
            Carbon::parse($data['ends_at'])
        );

        return back()->with('notice', 'Бронирование создано.');
    }

    public function destroy(Request $request, Event $event)
    {
        $user = $request->user();
        if (!$user || !$this->canDelete($user, $event)) {
            abort(403);
        }

        if ($event->bookings()->where('status', 'approved')->exists()) {
            return back()->withErrors([
                'event' => 'Нельзя удалить событие с подтвержденными бронированиями.',
            ]);
        }

        $event->delete();

        return redirect()->route('events.index')->with('notice', 'Событие удалено.');
    }

    private function canDelete(?\App\Models\User $user, Event $event): bool
    {
        if (!$user) {
            return false;
        }

        $isAdmin = $user->roles()->where('alias', 'admin')->exists();

        return $isAdmin || $event->organizer_id === $user->id;
    }
}
