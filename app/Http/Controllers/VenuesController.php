<?php

namespace App\Http\Controllers;

use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Contracts\Enums\ContractType;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Contracts\Services\ContractManager;
use App\Domain\Moderation\Enums\ModerationEntityType;
use App\Domain\Moderation\UseCases\SubmitModerationRequest;
use App\Domain\Permissions\Enums\PermissionScope;
use App\Domain\Permissions\Enums\PermissionCode;
use App\Domain\Permissions\Models\Permission;
use App\Domain\Permissions\Registry\PermissionRegistry;
use App\Domain\Permissions\Services\PermissionChecker;
use App\Domain\Events\Models\EventBooking;
use App\Domain\Venues\Enums\VenueStatus;
use App\Domain\Venues\Models\Venue;
use App\Domain\Venues\Models\VenueSchedule;
use App\Domain\Venues\Models\VenueScheduleException;
use App\Domain\Venues\Models\VenueScheduleExceptionInterval;
use App\Domain\Venues\Models\VenueScheduleInterval;
use App\Domain\Venues\Services\VenueCatalogService;
use App\Domain\Venues\UseCases\CreateVenue;
use App\Domain\Venues\UseCases\UpdateVenue;
use App\Domain\Users\Enums\UserStatus;
use App\Http\Requests\Venues\StoreVenueRequest;
use App\Presentation\Breadcrumbs\VenueBreadcrumbsPresenter;
use App\Presentation\Navigation\VenueNavigationPresenter;
use App\Presentation\Venues\MetroOptionsPresenter;
use App\Presentation\Venues\VenueShowPresenter;
use App\Presentation\Venues\VenueSidebarPresenter;
use App\Presentation\Venues\VenueTypeOptionsPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class VenuesController extends Controller
{
    public function index()
    {
        $user = request()->user();

        $navigation = app(VenueNavigationPresenter::class)->present([
            'title' => 'Площадки',
        ]);

        $types = app(VenueTypeOptionsPresenter::class)->present()['data'];
        $catalog = app(VenueCatalogService::class);
        $catalogData = $catalog->getHallsList(null, $user);
        $breadcrumbs = app(VenueBreadcrumbsPresenter::class)->present()['data'];

        return Inertia::render('Venues', [
            'appName' => config('app.name'),
            'venues' => $catalogData['venues'],
            'activeType' => $catalogData['activeType'],
            'activeTypeSlug' => $catalogData['activeTypeSlug'],
            'navigation' => $navigation,
            'types' => $types,
            'metros' => app(MetroOptionsPresenter::class)->present()['data'],
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function type(string $type)
    {
        $user = request()->user();

        $navigation = app(VenueNavigationPresenter::class)->present([
            'title' => 'Площадки',
        ]);
        $catalog = app(VenueCatalogService::class);
        $types = app(VenueTypeOptionsPresenter::class)->present()['data'];
        $catalogData = $catalog->getHallsList($type, $user);
        $breadcrumbs = app(VenueBreadcrumbsPresenter::class)->present([
            'typeSlug' => $type,
        ])['data'];

        if (!$catalogData) {
            abort(404);
        }

        return Inertia::render('Venues', [
            'appName' => config('app.name'),
            'venues' => $catalogData['venues'],
            'activeType' => $catalogData['activeType'],
            'activeTypeSlug' => $catalogData['activeTypeSlug'],
            'navigation' => $navigation,
            'types' => $types,
            'metros' => app(MetroOptionsPresenter::class)->present()['data'],
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function show(string $type, Venue $venue)
    {
        $user = request()->user();
        $venue = Venue::query()
            ->visibleFor($user)
            ->whereKey($venue->id)
            ->firstOrFail();
        $venue->load(['venueType:id,name,plural_name,alias', 'creator:id,login', 'latestAddress.metro:id,name,line_name,line_color,city']);

        $data = app(VenueShowPresenter::class)->present([
            'user' => $user,
            'venue' => $venue,
            'typeSlug' => $type,
        ])['data'];

        return Inertia::render('VenueShow', array_merge(
            ['appName' => config('app.name')],
            $data
        ));
    }

    public function contracts(Request $request, string $type, Venue $venue)
    {
        $user = $request->user();
        $manager = app(ContractManager::class);
        $venue = Venue::query()
            ->visibleFor($user)
            ->whereKey($venue->id)
            ->firstOrFail();
        $venue->loadMissing(['venueType:id,name,plural_name,alias']);

        if (!$this->canViewContracts($user, $venue)) {
            abort(403);
        }

        $checker = app(PermissionChecker::class);
        $canAssignContracts = $manager->canAssign($user, $venue);
        $assignableTypes = $manager->getAssignableTypes($user, $venue);
        $assignableTypeValues = array_map(static fn (ContractType $contractType) => $contractType->value, $assignableTypes);

        $navigation = app(VenueSidebarPresenter::class)->present([
            'title' => 'Площадки',
            'typeSlug' => $type,
            'venue' => $venue,
            'user' => $user,
        ]);
        $breadcrumbs = app(VenueBreadcrumbsPresenter::class)->present([
            'venue' => $venue,
            'typeSlug' => $type,
            'label' => 'Контракты',
        ])['data'];

        $this->ensureContractPermissions();

        $isAdmin = $user->roles()->where('alias', 'admin')->exists();
        $availablePermissions = Permission::query()
            ->where('scope', PermissionScope::Resource)
            ->where('target_model', Venue::class)
            ->orderBy('label')
            ->get(['code', 'label'])
            ->map(function (Permission $permission) use ($checker, $isAdmin, $user, $venue, $manager, $assignableTypes, $assignableTypeValues) {
                $code = $permission->code;

                if (!$isAdmin && !$checker->can($user, $code, $venue)) {
                    return null;
                }

                $allowedTypes = $assignableTypes;

                if ($code === PermissionCode::ContractAssign->value) {
                    $allowedTypes = array_filter(
                        $assignableTypes,
                        fn (ContractType $contractType) => $manager->canGrantContractAssign($user, $venue, $contractType)
                    );
                }

                if ($code === PermissionCode::ContractRevoke->value) {
                    $allowedTypes = array_filter(
                        $assignableTypes,
                        fn (ContractType $contractType) => $manager->canGrantContractRevoke($user, $venue, $contractType)
                    );
                }

                if ($allowedTypes === []) {
                    return null;
                }

                return [
                    'code' => $code,
                    'label' => $permission->label ?: $permission->code,
                    'allowed_types' => $allowedTypes === $assignableTypes
                        ? $assignableTypeValues
                        : array_map(static fn (ContractType $contractType) => $contractType->value, $allowedTypes),
                ];
            })
            ->filter()
            ->values()
            ->all();

        $currentUserId = $user?->id;
        $contracts = Contract::query()
            ->where('entity_type', $venue->getMorphClass())
            ->where('entity_id', $venue->getKey())
            ->with(['user:id,login', 'permissions:id,code,label'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Contract $contract) use ($manager, $user, $venue, $currentUserId): array {
                $contractUserId = $contract->user?->id;

                return [
                    'id' => $contract->id,
                    'user_id' => $contractUserId,
                    'name' => $contract->name,
                    'contract_type' => $contract->contract_type?->value,
                    'status' => $contract->status?->value,
                    'starts_at' => $contract->starts_at?->toDateTimeString(),
                    'ends_at' => $contract->ends_at?->toDateTimeString(),
                    'comment' => $contract->comment,
                    'created_by' => $contract->created_by,
                    'created_at' => $contract->created_at?->timestamp,
                    'is_current_user' => $currentUserId !== null && $contractUserId === $currentUserId,
                    'can_revoke' => $manager->canRevoke($user, $contract, $venue),
                    'can_update_permissions' => $manager->canUpdatePermissions($user, $contract, $venue),
                    'user' => $contract->user
                        ? [
                            'id' => $contract->user->id,
                            'login' => $contract->user->login,
                        ]
                        : null,
                    'permissions' => $contract->permissions
                        ->filter(static fn ($permission) => (bool) $permission->pivot?->is_active)
                        ->map(static fn ($permission) => [
                            'code' => $permission->code,
                            'label' => $permission->label ?: $permission->code,
                        ])
                        ->all(),
                ];
            })
            ->sortBy(function (array $contract) use ($currentUserId): array {
                $type = $contract['contract_type'] ?? null;

                if ($type === ContractType::Creator->value) {
                    $group = 0;
                } elseif ($type === ContractType::Owner->value) {
                    $group = 1;
                } elseif ($currentUserId && ($contract['user_id'] ?? null) === $currentUserId) {
                    $group = 2;
                } else {
                    $group = 3;
                }

                $createdAt = $contract['created_at'] ?? 0;

                return [$group, -$createdAt];
            })
            ->values()
            ->all();

        return Inertia::render('VenueContracts', [
            'appName' => config('app.name'),
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'alias' => $venue->alias,
            ],
            'contracts' => $contracts,
            'availablePermissions' => $availablePermissions,
            'contractTypes' => collect($assignableTypes)
                ->map(fn (ContractType $contractType) => [
                    'value' => $contractType->value,
                    'label' => $contractType->label(),
                ])
                ->all(),
            'canAssignContracts' => $canAssignContracts,
            'navigation' => $navigation,
            'activeHref' => "/venues/{$type}/{$venue->alias}/contracts",
            'activeTypeSlug' => $type,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function schedule(Request $request, string $type, Venue $venue)
    {
        $user = $request->user();
        $venue = Venue::query()
            ->visibleFor($user)
            ->whereKey($venue->id)
            ->firstOrFail();
        $venue->loadMissing(['venueType:id,name,plural_name,alias']);

        $schedule = VenueSchedule::query()
            ->where('venue_id', $venue->id)
            ->with(['intervals', 'exceptions.intervals'])
            ->first();

        $navigation = app(VenueSidebarPresenter::class)->present([
            'title' => 'Площадки',
            'typeSlug' => $type,
            'venue' => $venue,
            'user' => $user,
        ]);
        $breadcrumbs = app(VenueBreadcrumbsPresenter::class)->present([
            'venue' => $venue,
            'typeSlug' => $type,
            'label' => 'Расписание',
        ])['data'];

        $weeklyIntervals = [];
        if ($schedule) {
            $weeklyIntervals = $schedule->intervals
                ->groupBy('day_of_week')
                ->map(function ($items) {
                    return $items
                        ->sortBy('starts_at')
                        ->map(fn (VenueScheduleInterval $interval) => [
                            'id' => $interval->id,
                            'day_of_week' => $interval->day_of_week,
                            'starts_at' => $this->formatTime($interval->starts_at),
                            'ends_at' => $this->formatTime($interval->ends_at),
                        ])
                        ->values()
                        ->all();
                })
                ->all();
        }

        $exceptions = [];
        if ($schedule) {
            $exceptions = $schedule->exceptions
                ->sortBy('date')
                ->map(function (VenueScheduleException $exception) {
                    return [
                        'id' => $exception->id,
                        'date' => $exception->date?->toDateString(),
                        'is_closed' => (bool) $exception->is_closed,
                        'comment' => $exception->comment,
                        'intervals' => $exception->intervals
                            ->sortBy('starts_at')
                            ->map(fn (VenueScheduleExceptionInterval $interval) => [
                                'id' => $interval->id,
                                'starts_at' => $this->formatTime($interval->starts_at),
                                'ends_at' => $this->formatTime($interval->ends_at),
                            ])
                            ->values()
                            ->all(),
                    ];
                })
                ->values()
                ->all();
        }

        $bookingDates = EventBooking::query()
            ->where('venue_id', $venue->id)
            ->whereIn('status', ['pending', 'approved'])
            ->orderBy('starts_at')
            ->get(['starts_at'])
            ->map(static fn (EventBooking $booking) => $booking->starts_at?->toDateString())
            ->filter()
            ->unique()
            ->values()
            ->all();

        $checker = app(PermissionChecker::class);
        $canManage = $user
            && $checker->can($user, PermissionCode::VenueScheduleManage, $venue);

        return Inertia::render('VenueSchedule', [
            'appName' => config('app.name'),
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'alias' => $venue->alias,
            ],
            'schedule' => $schedule
                ? [
                    'id' => $schedule->id,
                    'timezone' => $schedule->timezone,
                ]
                : null,
            'weeklyIntervals' => $weeklyIntervals,
            'exceptions' => $exceptions,
            'bookingDates' => $bookingDates,
            'canManage' => $canManage,
            'daysOfWeek' => $this->weekDays(),
            'navigation' => $navigation,
            'activeHref' => "/venues/{$type}/{$venue->alias}/schedule",
            'activeTypeSlug' => $type,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function bookings(Request $request, string $type, Venue $venue)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $venue = Venue::query()
            ->visibleFor($user)
            ->whereKey($venue->id)
            ->firstOrFail();
        $venue->loadMissing(['venueType:id,name,plural_name,alias']);

        $checker = app(PermissionChecker::class);
        $canConfirm = $checker->can($user, PermissionCode::VenueBookingConfirm, $venue);
        $canCancel = $checker->can($user, PermissionCode::VenueBookingCancel, $venue);
        $isAdmin = $user->roles()->where('alias', 'admin')->exists();

        if (!$canConfirm && !$canCancel) {
            abort(403);
        }

        $now = now();
        $status = $request->string('status')->toString();
        $allowedStatuses = ['pending', 'approved', 'cancelled'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $bookings = EventBooking::query()
            ->where('venue_id', $venue->id)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->with(['event.type', 'event.organizer', 'creator', 'moderator'])
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->through(function (EventBooking $booking) use ($canConfirm, $canCancel, $isAdmin, $now): array {
                $status = $booking->status;
                $isPast = $booking->starts_at && $booking->starts_at->lt($now);
                return [
                    'id' => $booking->id,
                    'status' => $status,
                    'starts_at' => $booking->starts_at?->toDateTimeString(),
                    'ends_at' => $booking->ends_at?->toDateTimeString(),
                    'event' => $booking->event
                        ? [
                            'id' => $booking->event->id,
                            'title' => $booking->event->title ?: 'Событие',
                            'type' => $booking->event->type
                                ? [
                                    'code' => $booking->event->type->code,
                                    'label' => $booking->event->type->label,
                                ]
                                : null,
                            'organizer' => $booking->event->organizer
                                ? [
                                    'id' => $booking->event->organizer->id,
                                    'login' => $booking->event->organizer->login,
                                ]
                                : null,
                        ]
                        : null,
                    'creator' => $booking->creator
                        ? [
                            'id' => $booking->creator->id,
                            'login' => $booking->creator->login,
                        ]
                        : null,
                    'moderator' => $booking->moderator
                        ? [
                            'id' => $booking->moderator->id,
                            'login' => $booking->moderator->login,
                        ]
                        : null,
                    'moderated_at' => $booking->moderated_at?->toDateTimeString(),
                    'can_confirm' => !$isPast && $canConfirm && $status === 'pending',
                    'can_cancel' => !$isPast && $canCancel && ($status === 'pending' || ($status === 'approved' && $isAdmin)),
                ];
            });

        $navigation = app(VenueSidebarPresenter::class)->present([
            'title' => 'Площадки',
            'typeSlug' => $type,
            'venue' => $venue,
            'user' => $user,
        ]);
        $breadcrumbs = app(VenueBreadcrumbsPresenter::class)->present([
            'venue' => $venue,
            'typeSlug' => $type,
            'label' => 'Бронирование',
        ])['data'];

        return Inertia::render('VenueBookings', [
            'appName' => config('app.name'),
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'alias' => $venue->alias,
            ],
            'bookings' => $bookings,
            'filters' => [
                'status' => $status,
            ],
            'canConfirm' => $canConfirm,
            'canCancel' => $canCancel,
            'navigation' => $navigation,
            'activeHref' => "/venues/{$type}/{$venue->alias}/bookings",
            'activeTypeSlug' => $type,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function confirmBooking(Request $request, string $type, Venue $venue, EventBooking $booking)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $venue = Venue::query()
            ->visibleFor($user)
            ->whereKey($venue->id)
            ->firstOrFail();

        $checker = app(PermissionChecker::class);
        if (!$checker->can($user, PermissionCode::VenueBookingConfirm, $venue)) {
            abort(403);
        }

        if ($booking->venue_id !== $venue->id) {
            abort(404);
        }

        if ($booking->starts_at && $booking->starts_at->lt(now())) {
            return back()->withErrors([
                'booking' => 'Нельзя подтверждать бронирование в прошлом.',
            ]);
        }

        if ($booking->status !== 'pending') {
            return back()->withErrors([
                'booking' => 'Подтвердить можно только заявку в статусе ожидания.',
            ]);
        }

        $data = $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $booking->update([
            'status' => 'approved',
            'moderation_comment' => $data['comment'] ?? null,
            'moderated_by' => $user->id,
            'moderated_at' => now(),
        ]);

        return back()->with('notice', 'Бронирование подтверждено.');
    }

    public function cancelBooking(Request $request, string $type, Venue $venue, EventBooking $booking)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $venue = Venue::query()
            ->visibleFor($user)
            ->whereKey($venue->id)
            ->firstOrFail();

        $checker = app(PermissionChecker::class);
        if (!$checker->can($user, PermissionCode::VenueBookingCancel, $venue)) {
            abort(403);
        }

        if ($booking->venue_id !== $venue->id) {
            abort(404);
        }

        if ($booking->starts_at && $booking->starts_at->lt(now())) {
            return back()->withErrors([
                'booking' => 'Нельзя отменять бронирование в прошлом.',
            ]);
        }

        if (!in_array($booking->status, ['pending', 'approved'], true)) {
            return back()->withErrors([
                'booking' => 'Отменить можно только активную заявку.',
            ]);
        }

        if ($booking->status === 'approved') {
            $isAdmin = $user->roles()->where('alias', 'admin')->exists();
            if (!$isAdmin) {
                return back()->withErrors([
                    'booking' => 'Отменить подтвержденное бронирование может только администратор.',
                ]);
            }
        }

        $data = $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $booking->update([
            'status' => 'cancelled',
            'moderation_comment' => $data['comment'] ?? null,
            'moderated_by' => $user->id,
            'moderated_at' => now(),
        ]);

        return back()->with('notice', 'Бронирование отменено.');
    }

    public function storeScheduleInterval(Request $request, string $type, Venue $venue)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $venue = Venue::query()
            ->visibleFor($user)
            ->whereKey($venue->id)
            ->firstOrFail();

        $this->ensureCanManageSchedule($user, $venue);

        $data = $request->validate([
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
        ]);

        $schedule = $this->resolveSchedule($venue);
        $this->ensureIntervalValid($data['starts_at'], $data['ends_at']);
        $this->ensureWeeklyIntervalAvailability($schedule, $data['day_of_week'], $data['starts_at'], $data['ends_at']);

        $schedule->intervals()->create([
            'day_of_week' => $data['day_of_week'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
        ]);

        return back()->with('notice', 'Интервал добавлен.');
    }

    public function updateScheduleInterval(Request $request, string $type, Venue $venue, VenueScheduleInterval $interval)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $venue = Venue::query()
            ->visibleFor($user)
            ->whereKey($venue->id)
            ->firstOrFail();

        $this->ensureCanManageSchedule($user, $venue);

        $schedule = $interval->schedule;
        if (!$schedule || $schedule->venue_id !== $venue->id) {
            abort(404);
        }

        $data = $request->validate([
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
        ]);

        $this->ensureIntervalValid($data['starts_at'], $data['ends_at']);
        $this->ensureWeeklyIntervalAvailability($schedule, $data['day_of_week'], $data['starts_at'], $data['ends_at'], $interval->id);

        $interval->update([
            'day_of_week' => $data['day_of_week'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
        ]);

        return back()->with('notice', 'Интервал обновлен.');
    }

    public function destroyScheduleInterval(Request $request, string $type, Venue $venue, VenueScheduleInterval $interval)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $venue = Venue::query()
            ->visibleFor($user)
            ->whereKey($venue->id)
            ->firstOrFail();

        $this->ensureCanManageSchedule($user, $venue);

        $schedule = $interval->schedule;
        if (!$schedule || $schedule->venue_id !== $venue->id) {
            abort(404);
        }

        $interval->delete();

        return back()->with('notice', 'Интервал удален.');
    }

    public function storeScheduleException(Request $request, string $type, Venue $venue)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $venue = Venue::query()
            ->visibleFor($user)
            ->whereKey($venue->id)
            ->firstOrFail();

        $this->ensureCanManageSchedule($user, $venue);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'is_closed' => ['boolean'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'intervals' => ['array'],
            'intervals.*.starts_at' => ['required_with:intervals', 'date_format:H:i'],
            'intervals.*.ends_at' => ['required_with:intervals', 'date_format:H:i'],
        ]);

        $schedule = $this->resolveSchedule($venue);
        $isClosed = (bool) ($data['is_closed'] ?? false);
        $intervals = $data['intervals'] ?? [];

        if ($isClosed && $intervals !== []) {
            throw ValidationException::withMessages([
                'intervals' => 'Для закрытой даты интервалы не задаются.',
            ]);
        }

        if (!$isClosed && $intervals === []) {
            throw ValidationException::withMessages([
                'intervals' => 'Добавьте хотя бы один интервал.',
            ]);
        }

        $this->ensureExceptionIntervalsValid($intervals);

        $exists = VenueScheduleException::query()
            ->where('schedule_id', $schedule->id)
            ->where('date', $data['date'])
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'date' => 'Исключение для этой даты уже существует.',
            ]);
        }

        $exception = $schedule->exceptions()->create([
            'date' => $data['date'],
            'is_closed' => $isClosed,
            'comment' => $data['comment'] ?? null,
        ]);

        if (!$isClosed && $intervals !== []) {
            foreach ($intervals as $interval) {
                $exception->intervals()->create([
                    'starts_at' => $interval['starts_at'],
                    'ends_at' => $interval['ends_at'],
                ]);
            }
        }

        return back()->with('notice', 'Исключение добавлено.');
    }

    public function updateScheduleException(Request $request, string $type, Venue $venue, VenueScheduleException $exception)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $venue = Venue::query()
            ->visibleFor($user)
            ->whereKey($venue->id)
            ->firstOrFail();

        $this->ensureCanManageSchedule($user, $venue);

        $schedule = $exception->schedule;
        if (!$schedule || $schedule->venue_id !== $venue->id) {
            abort(404);
        }

        $data = $request->validate([
            'date' => ['required', 'date'],
            'is_closed' => ['boolean'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'intervals' => ['array'],
            'intervals.*.starts_at' => ['required_with:intervals', 'date_format:H:i'],
            'intervals.*.ends_at' => ['required_with:intervals', 'date_format:H:i'],
        ]);

        $isClosed = (bool) ($data['is_closed'] ?? false);
        $intervals = $data['intervals'] ?? [];

        if ($isClosed && $intervals !== []) {
            throw ValidationException::withMessages([
                'intervals' => 'Для закрытой даты интервалы не задаются.',
            ]);
        }

        if (!$isClosed && $intervals === []) {
            throw ValidationException::withMessages([
                'intervals' => 'Добавьте хотя бы один интервал.',
            ]);
        }

        $this->ensureExceptionIntervalsValid($intervals);

        $dateExists = VenueScheduleException::query()
            ->where('schedule_id', $schedule->id)
            ->where('date', $data['date'])
            ->where('id', '!=', $exception->id)
            ->exists();
        if ($dateExists) {
            throw ValidationException::withMessages([
                'date' => 'Исключение для этой даты уже существует.',
            ]);
        }

        $exception->update([
            'date' => $data['date'],
            'is_closed' => $isClosed,
            'comment' => $data['comment'] ?? null,
        ]);

        $exception->intervals()->delete();

        if (!$isClosed && $intervals !== []) {
            foreach ($intervals as $interval) {
                $exception->intervals()->create([
                    'starts_at' => $interval['starts_at'],
                    'ends_at' => $interval['ends_at'],
                ]);
            }
        }

        return back()->with('notice', 'Исключение обновлено.');
    }

    public function destroyScheduleException(Request $request, string $type, Venue $venue, VenueScheduleException $exception)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $venue = Venue::query()
            ->visibleFor($user)
            ->whereKey($venue->id)
            ->firstOrFail();

        $this->ensureCanManageSchedule($user, $venue);

        $schedule = $exception->schedule;
        if (!$schedule || $schedule->venue_id !== $venue->id) {
            abort(404);
        }

        $exception->delete();

        return back()->with('notice', 'Исключение удалено.');
    }

    public function updateContractPermissions(
        Request $request,
        string $type,
        Venue $venue,
        Contract $contract,
        ContractManager $manager
    ) {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $venue = Venue::query()
            ->visibleFor($user)
            ->whereKey($venue->id)
            ->firstOrFail();

        if ($contract->entity_type !== $venue->getMorphClass() || $contract->entity_id !== $venue->getKey()) {
            abort(404);
        }

        $data = validator($request->all(), [
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,code'],
        ])->validate();

        $result = $manager->updatePermissions($user, $contract, $venue, $data['permissions'] ?? []);

        if (!$result->success) {
            return back()->withErrors(['contract' => $result->error ?? 'Не удалось обновить права контракта.']);
        }

        return back();
    }

    public function assignContract(Request $request, string $type, Venue $venue, ContractManager $manager)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $venue = Venue::query()
            ->visibleFor($user)
            ->whereKey($venue->id)
            ->firstOrFail();

        if (!$manager->canAssign($user, $venue)) {
            abort(403);
        }

        $this->ensureContractPermissions();

        $assignableTypes = $manager->getAssignableTypes($user, $venue);
        $assignableTypeValues = array_map(static fn (ContractType $contractType) => $contractType->value, $assignableTypes);
        $assignableTypeRule = $assignableTypeValues === [] ? 'in:' : 'in:' . implode(',', $assignableTypeValues);

        $data = $request->validate([
            'login' => ['nullable', 'string'],
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('status', UserStatus::Confirmed->value),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'contract_type' => ['required', 'string', $assignableTypeRule],
            'starts_at' => ['required', 'date', 'after_or_equal:today'],
            'ends_at' => ['nullable', 'date'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,code'],
        ]);

        $target = \App\Models\User::query()
            ->whereKey($data['user_id'])
            ->where('status', UserStatus::Confirmed->value)
            ->first();

        if (!$target) {
            return back()->withErrors(['contract' => 'Пользователь не найден.']);
        }

        $startsAt = !empty($data['starts_at']) ? Carbon::parse($data['starts_at']) : null;
        $endsAt = !empty($data['ends_at']) ? Carbon::parse($data['ends_at']) : null;
        $contractType = ContractType::from($data['contract_type']);

        $result = $manager->assign(
            $user,
            $target,
            $venue,
            $contractType,
            $data['permissions'] ?? [],
            $data['name'] ?? null,
            $startsAt,
            $endsAt,
            $data['comment'] ?? null
        );

        if (!$result->success) {
            return back()->withErrors(['contract' => 'Пользователь не найден.']);
        }

        return back()->with('notice', 'Контракт назначен.');
    }

    public function revokeContract(Request $request, string $type, Venue $venue, Contract $contract, ContractManager $manager)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $venue = Venue::query()
            ->visibleFor($user)
            ->whereKey($venue->id)
            ->firstOrFail();

        if (
            $contract->entity_type !== $venue->getMorphClass()
            || $contract->entity_id !== $venue->getKey()
        ) {
            abort(404);
        }

        $result = $manager->revoke($user, $contract, $venue);

        if (!$result->success) {
            return back()->withErrors(['contract' => 'Пользователь не найден.']);
        }

        return back()->with('notice', 'Контракт аннулирован.');
    }

    private function canViewContracts(?\App\Models\User $user, Venue $venue): bool
    {
        if (!$user) {
            return false;
        }

        $isAdmin = $user->roles()
            ->where('alias', 'admin')
            ->exists();

        if ($isAdmin) {
            return true;
        }

        $now = now();

        return Contract::query()
            ->where('user_id', $user->id)
            ->where('entity_type', $venue->getMorphClass())
            ->where('entity_id', $venue->getKey())
            ->where('status', ContractStatus::Active->value)
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->exists();
    }


    private function ensureContractPermissions(): void
    {
        $definitions = array_filter(
            PermissionRegistry::all(),
            static fn (array $definition) => in_array(
                $definition['code'],
                [PermissionCode::ContractAssign, PermissionCode::ContractRevoke],
                true
            )
        );

        foreach ($definitions as $definition) {
            $code = $definition['code'] instanceof \BackedEnum
                ? $definition['code']->value
                : $definition['code'];

            $scope = $definition['scope'] instanceof \BackedEnum
                ? $definition['scope']->value
                : $definition['scope'];

            Permission::query()->updateOrCreate(
                ['code' => $code],
                [
                    'label' => $definition['label'],
                    'scope' => $scope,
                    'target_model' => $definition['target_model'],
                ]
            );
        }
    }

    private function resolveSchedule(Venue $venue): VenueSchedule
    {
        return VenueSchedule::query()->firstOrCreate(
            ['venue_id' => $venue->id],
            ['timezone' => 'UTC+3']
        );
    }

    private function ensureCanManageSchedule(\App\Models\User $user, Venue $venue): void
    {
        $checker = app(PermissionChecker::class);
        if (!$checker->can($user, PermissionCode::VenueScheduleManage, $venue)) {
            abort(403);
        }
    }

    private function ensureIntervalValid(string $startsAt, string $endsAt): void
    {
        if ($this->timeToMinutes($startsAt) >= $this->timeToMinutes($endsAt)) {
            throw ValidationException::withMessages([
                'starts_at' => 'Время начала должно быть меньше времени окончания.',
            ]);
        }
    }

    private function ensureWeeklyIntervalAvailability(
        VenueSchedule $schedule,
        int $dayOfWeek,
        string $startsAt,
        string $endsAt,
        ?int $ignoreId = null
    ): void {
        $existing = $schedule->intervals()
            ->where('day_of_week', $dayOfWeek)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->get(['starts_at', 'ends_at']);

        foreach ($existing as $interval) {
            if ($this->hasOverlap($startsAt, $endsAt, $interval->starts_at, $interval->ends_at)) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Интервал пересекается с существующим.',
                ]);
            }
        }
    }

    private function ensureExceptionIntervalsValid(array $intervals): void
    {
        if ($intervals === []) {
            return;
        }

        $normalized = [];
        foreach ($intervals as $interval) {
            $startsAt = $interval['starts_at'] ?? '';
            $endsAt = $interval['ends_at'] ?? '';
            $this->ensureIntervalValid($startsAt, $endsAt);
            $normalized[] = [$startsAt, $endsAt];
        }

        $count = count($normalized);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($this->hasOverlap($normalized[$i][0], $normalized[$i][1], $normalized[$j][0], $normalized[$j][1])) {
                    throw ValidationException::withMessages([
                        'intervals' => 'Интервалы исключения пересекаются.',
                    ]);
                }
            }
        }
    }

    private function hasOverlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        $aStart = $this->timeToMinutes($startA);
        $aEnd = $this->timeToMinutes($endA);
        $bStart = $this->timeToMinutes($startB);
        $bEnd = $this->timeToMinutes($endB);

        return $aStart < $bEnd && $aEnd > $bStart;
    }

    private function timeToMinutes(string $time): int
    {
        $chunk = substr($time, 0, 5);
        [$hours, $minutes] = array_pad(explode(':', $chunk, 2), 2, 0);

        return ((int) $hours) * 60 + (int) $minutes;
    }

    private function formatTime(?string $time): string
    {
        if (!$time) {
            return '';
        }

        return substr($time, 0, 5);
    }

    private function weekDays(): array
    {
        return [
            ['value' => 1, 'label' => 'Понедельник'],
            ['value' => 2, 'label' => 'Вторник'],
            ['value' => 3, 'label' => 'Среда'],
            ['value' => 4, 'label' => 'Четверг'],
            ['value' => 5, 'label' => 'Пятница'],
            ['value' => 6, 'label' => 'Суббота'],
            ['value' => 7, 'label' => 'Воскресенье'],
        ];
    }

    public function store(StoreVenueRequest $request, CreateVenue $useCase)
    {
        $this->authorize('create', Venue::class);

        $user = $request->user();
        $data = $request->validated();
        $venue = $useCase->execute($user, $data);

        $typeSlug = $venue->venueType?->alias ? Str::plural($venue->venueType->alias) : '';

        return redirect()->route('venues.show', [
            'type' => $typeSlug,
            'venue' => $venue,
        ]);
    }

    public function update(Request $request, string $type, Venue $venue, UpdateVenue $useCase)
    {
        $this->authorize('update', $venue);

        $fields = $useCase->getEditableFields($venue);
        if ($fields === []) {
            return back()->withErrors(['venue' => 'Редактирование недоступно для этой площадки.']);
        }

        $rules = Arr::only($useCase->getValidationRules($venue), $fields);
        $data = validator($request->only($fields), $rules)->validate();
        $useCase->execute($request->user(), $venue, $data);

        return back();
    }

    public function submitModerationRequest(Request $request, string $type, Venue $venue, SubmitModerationRequest $useCase)
    {
        $user = $request->user();
        $checker = app(PermissionChecker::class);
        $canSubmitModeration = $user
            && $user->status?->value === UserStatus::Confirmed->value
            && $checker->can($user, PermissionCode::VenueSubmitForModeration, $venue);

        if (!$canSubmitModeration) {
            return back()->withErrors([
                'moderation' => 'Недостаточно прав для отправки на модерацию.',
            ]);
        }

        $result = $useCase->execute($user, ModerationEntityType::Venue, $venue);

        if (!$result->success) {
            return back()->withErrors([
                'moderation' => implode("\n", $result->missingRequirements ?? []),
            ]);
        }

        $venue->update([
            'status' => VenueStatus::Moderation,
        ]);

        return back();
    }

    // Методы генерации alias перенесены в доменный use case.
}
