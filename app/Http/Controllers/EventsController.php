<?php

namespace App\Http\Controllers;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Services\EventBookingService;
use App\Domain\Permissions\Enums\PermissionCode;
use App\Domain\Permissions\Services\PermissionChecker;
use App\Domain\Venues\Models\Venue;
use App\Presentation\Breadcrumbs\EventBreadcrumbsPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class EventsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $checker = app(PermissionChecker::class);

        $events = Event::query()
            ->with(['type', 'organizer'])
            ->orderByDesc('created_at')
            ->get()
            ->map(static function (Event $event): array {
                return [
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
        $breadcrumbs = app(EventBreadcrumbsPresenter::class)->present()['data'];

        return Inertia::render('Events', [
            'appName' => config('app.name'),
            'events' => $events,
            'eventTypes' => $eventTypes,
            'canCreate' => $canCreate,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $data = $request->validate(
            [
                'event_type_id' => ['required', 'integer', 'exists:event_types,id'],
                'title' => ['nullable', 'string', 'max:255'],
                'starts_at' => ['required', 'date'],
                'ends_at' => ['required', 'date'],
            ]
        );

        $timezone = config('app.timezone');
        $startsAt = Carbon::parse($data['starts_at'], $timezone);
        $endsAt = Carbon::parse($data['ends_at'], $timezone);
        $minStart = Carbon::now($timezone)->addMinutes(15);

        if ($startsAt->lt($minStart)) {
            return back()->withErrors([
                'starts_at' => 'Событие должно начинаться не ранее чем ' . $minStart->format('d.m.Y H:i') . '.',
            ]);
        }

        if ($endsAt->lt($startsAt->copy()->addMinutes(15))) {
            return back()->withErrors(['ends_at' => 'Длительность события должна быть не менее 15 минут.']);
        }

        $type = EventType::query()->find($data['event_type_id']);
        if (!$type) {
            return back()->withErrors(['event_type_id' => 'Тип события не найден.']);
        }

        $title = $data['title'] ?: $type->label;

        $event = Event::query()->create([
            'event_type_id' => $type->id,
            'organizer_id' => $user->id,
            'status' => 'draft',
            'title' => $title,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'timezone' => 'UTC+3',
        ]);

        return redirect()->route('events.show', $event)->with('notice', 'Событие создано.');
    }

    public function show(Request $request, Event $event)
    {
        $user = $request->user();
        $checker = app(PermissionChecker::class);

        $event->loadMissing(['type', 'organizer', 'bookings.venue']);

        $bookings = $event->bookings
            ->map(static function ($booking): array {
                return [
                    'id' => $booking->id,
                    'status' => $booking->status,
                    'starts_at' => $booking->starts_at?->toDateTimeString(),
                    'ends_at' => $booking->ends_at?->toDateTimeString(),
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

        $venues = Venue::query()
            ->visibleFor($user)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Venue $venue) => [
                'id' => $venue->id,
                'name' => $venue->name,
            ])
            ->all();

        $canBook = $user && $checker->can($user, PermissionCode::VenueBooking);
        $canDelete = $this->canDelete($user, $event);
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
            'venues' => $venues,
            'canBook' => $canBook,
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
