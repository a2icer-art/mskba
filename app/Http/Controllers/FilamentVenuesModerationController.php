<?php

namespace App\Http\Controllers;

use App\Domain\Filament\Services\FilamentNavigationService;
use App\Domain\Moderation\Enums\ModerationEntityType;
use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Models\ModerationRequest;
use App\Domain\Venues\Enums\VenueStatus;
use App\Support\DateFormatter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FilamentVenuesModerationController extends Controller
{
    public function index(Request $request)
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 10);

        if ($roleLevel <= 20) {
            abort(403);
        }

        $navigation = app(FilamentNavigationService::class);
        $items = $navigation->getMenuGroups($roleLevel);

        $status = $request->string('status')->toString();
        $sort = $request->string('sort', 'submitted_at_desc')->toString();
        $allowedStatuses = [
            ModerationStatus::Pending->value,
            ModerationStatus::Approved->value,
            ModerationStatus::Rejected->value,
        ];
        $allowedSorts = ['submitted_at_desc', 'submitted_at_asc'];

        $query = ModerationRequest::query()
            ->where('entity_type', ModerationEntityType::Venue->value)
            ->with([
                'entityVenue.venueType:id,name,alias',
                'entityVenue.creator:id,login',
                'entityVenue.latestAddress',
                'reviewer:id,login',
            ]);

        if (in_array($status, $allowedStatuses, true)) {
            $query->where('status', $status);
        }

        if ($sort === 'submitted_at_asc') {
            $query->orderBy('submitted_at');
        } else {
            $query->orderByDesc('submitted_at');
        }

        $requests = $query
            ->paginate(10)
            ->withQueryString()
            ->through(function (ModerationRequest $request) {
                $venue = $request->entityVenue;
                $reviewer = $request->reviewer;

                return [
                    'id' => $request->id,
                    'status' => $request->status?->value,
                    'submitted_at' => DateFormatter::dateTime($request->submitted_at),
                    'reviewed_at' => DateFormatter::dateTime($request->reviewed_at),
                    'reject_reason' => $request->reject_reason,
                    'reviewer' => $reviewer
                        ? [
                            'id' => $reviewer->id,
                            'login' => $reviewer->login,
                        ]
                        : null,
                    'venue' => $venue
                        ? [
                            'id' => $venue->id,
                            'name' => $venue->name,
                            'status' => $venue->status?->value,
                            'address' => $venue->latestAddress?->display_address,
                            'created_at' => DateFormatter::dateTime($venue->created_at),
                        ]
                        : null,
                    'type' => $venue?->venueType
                        ? [
                            'id' => $venue->venueType->id,
                            'name' => $venue->venueType->name,
                        ]
                        : null,
                    'creator' => $venue?->creator
                        ? [
                            'id' => $venue->creator->id,
                            'login' => $venue->creator->login,
                        ]
                        : null,
                ];
            });

        return Inertia::render('Filament/VenuesModeration', [
            'appName' => config('app.name'),
            'navigation' => [
                'title' => 'Разделы',
                'items' => $items,
            ],
            'activeHref' => '/filament/venues-moderation',
            'filters' => [
                'status' => $status,
                'sort' => $sort,
            ],
            'statusOptions' => [
                ['value' => '', 'label' => 'Все'],
                ['value' => ModerationStatus::Pending->value, 'label' => 'На модерации'],
                ['value' => ModerationStatus::Approved->value, 'label' => 'Подтверждено'],
                ['value' => ModerationStatus::Rejected->value, 'label' => 'Отклонено'],
            ],
            'sortOptions' => [
                ['value' => 'submitted_at_desc', 'label' => 'Сначала новые'],
                ['value' => 'submitted_at_asc', 'label' => 'Сначала старые'],
            ],
            'requests' => $requests,
        ]);
    }

    public function approve(Request $request, ModerationRequest $moderationRequest)
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 10);

        if ($roleLevel <= 20) {
            abort(403);
        }

        if ($moderationRequest->entity_type !== ModerationEntityType::Venue) {
            abort(404);
        }

        if ($moderationRequest->status !== ModerationStatus::Pending) {
            return back()->withErrors(['moderation' => 'Заявка уже обработана.']);
        }

        $venue = $moderationRequest->entityVenue;
        if (!$venue) {
            return back()->withErrors(['moderation' => 'Площадка не найдена.']);
        }

        if ($venue->status?->value === VenueStatus::Confirmed->value) {
            return back()->withErrors(['moderation' => 'Площадка уже подтверждена.']);
        }

        $moderationRequest->update([
            'status' => ModerationStatus::Approved,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'reject_reason' => null,
        ]);

        $venue->update([
            'status' => VenueStatus::Confirmed,
            'confirmed_at' => now(),
            'confirmed_by' => $request->user()?->id,
        ]);

        return back();
    }

    public function reject(Request $request, ModerationRequest $moderationRequest)
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 10);

        if ($roleLevel <= 20) {
            abort(403);
        }

        if ($moderationRequest->entity_type !== ModerationEntityType::Venue) {
            abort(404);
        }

        if ($moderationRequest->status !== ModerationStatus::Pending) {
            return back()->withErrors(['moderation' => 'Заявка уже обработана.']);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $moderationRequest->update([
            'status' => ModerationStatus::Rejected,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'reject_reason' => $data['reason'] ?? null,
        ]);

        $venue = $moderationRequest->entityVenue;
        if ($venue) {
            $venue->update([
                'status' => VenueStatus::Unconfirmed,
                'confirmed_at' => null,
                'confirmed_by' => null,
            ]);
        }

        return back();
    }

    public function block(Request $request, ModerationRequest $moderationRequest)
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 10);

        if ($roleLevel <= 20) {
            abort(403);
        }

        if ($moderationRequest->entity_type !== ModerationEntityType::Venue) {
            abort(404);
        }

        $venue = $moderationRequest->entityVenue;
        if (!$venue) {
            return back()->withErrors(['moderation' => 'Площадка не найдена.']);
        }

        if ($venue->status?->value !== VenueStatus::Confirmed->value) {
            return back()->withErrors(['moderation' => 'Блокировать можно только подтвержденную площадку.']);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $venue->update([
            'status' => VenueStatus::Blocked,
            'blocked_at' => now(),
            'blocked_by' => $request->user()?->id,
            'block_reason' => $data['reason'] ?? null,
        ]);

        return back();
    }

    public function unblock(Request $request, ModerationRequest $moderationRequest)
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 10);

        if ($roleLevel <= 20) {
            abort(403);
        }

        if ($moderationRequest->entity_type !== ModerationEntityType::Venue) {
            abort(404);
        }

        $venue = $moderationRequest->entityVenue;
        if (!$venue) {
            return back()->withErrors(['moderation' => 'Площадка не найдена.']);
        }

        if ($venue->status?->value !== VenueStatus::Blocked->value) {
            return back()->withErrors(['moderation' => 'Площадка не заблокирована.']);
        }

        $venue->update([
            'status' => VenueStatus::Confirmed,
            'blocked_at' => null,
            'blocked_by' => null,
            'block_reason' => null,
        ]);

        return back();
    }

    private function getRoleLevel(Request $request): int
    {
        $user = $request->user();

        if (!$user) {
            abort(403);
        }

        return (int) $user->roles()->max('level');
    }

    private function ensureAccess(int $roleLevel, int $minLevel): void
    {
        if ($roleLevel <= $minLevel) {
            abort(403);
        }
    }
}
