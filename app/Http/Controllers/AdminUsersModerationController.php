<?php

namespace App\Http\Controllers;

use App\Domain\Moderation\Enums\ModerationEntityType;
use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Models\ModerationRequest;
use App\Domain\Users\Enums\UserConfirmedBy;
use App\Presentation\Navigation\AdminNavigationPresenter;
use App\Support\DateFormatter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminUsersModerationController extends Controller
{
    public function index(Request $request)
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 10);

        if ($roleLevel <= 20) {
            abort(403);
        }

        $navigation = app(AdminNavigationPresenter::class)->present([
            'roleLevel' => $roleLevel,
        ]);

        $status = $request->string('status')->toString();
        $sort = $request->string('sort', 'submitted_at_desc')->toString();
        $allowedStatuses = [
            ModerationStatus::Pending->value,
            ModerationStatus::Approved->value,
            ModerationStatus::Rejected->value,
        ];
        $allowedSorts = ['submitted_at_desc', 'submitted_at_asc'];

        $query = ModerationRequest::query()
            ->where('entity_type', ModerationEntityType::User->value)
            ->with([
                'entityUser.profile',
                'entityUser.contacts' => fn ($builder) => $builder->whereNotNull('confirmed_at'),
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
                $user = $request->entityUser;
                $profile = $user?->profile;
                $confirmedContact = $user?->contacts?->sortBy('id')->first();
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
                    'user' => $user
                        ? [
                            'id' => $user->id,
                            'login' => $user->login,
                            'status' => $user->status?->value,
                        ]
                        : null,
                    'profile' => $profile
                        ? [
                            'first_name' => $profile->first_name,
                            'last_name' => $profile->last_name,
                            'gender' => $profile->gender,
                            'birth_date' => DateFormatter::date($profile->birth_date),
                        ]
                        : null,
                    'contact' => $confirmedContact
                        ? [
                            'value' => $confirmedContact->value,
                            'confirmed_at' => DateFormatter::dateTime($confirmedContact->confirmed_at),
                        ]
                        : null,
                ];
            });

        return Inertia::render('Admin/UsersModeration', [
            'appName' => config('app.name'),
            'navigation' => $navigation,
            'activeHref' => '/admin/users-moderation',
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

        if ($moderationRequest->entity_type !== ModerationEntityType::User) {
            abort(404);
        }

        if ($moderationRequest->status !== ModerationStatus::Pending) {
            return back()->withErrors(['moderation' => 'Заявка уже обработана.']);
        }

        $user = $moderationRequest->entityUser;
        if (!$user) {
            return back()->withErrors(['moderation' => 'Пользователь не найден.']);
        }

        if ($user->status?->value === 'confirmed') {
            return back()->withErrors(['moderation' => 'Пользователь уже подтвержден.']);
        }

        $moderationRequest->update([
            'status' => ModerationStatus::Approved,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'reject_reason' => null,
        ]);

        $user->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'confirmed_by' => UserConfirmedBy::Admin,
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

        if ($moderationRequest->entity_type !== ModerationEntityType::User) {
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

        return back();
    }

    public function block(Request $request, ModerationRequest $moderationRequest)
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 10);

        if ($roleLevel <= 20) {
            abort(403);
        }

        if ($moderationRequest->entity_type !== ModerationEntityType::User) {
            abort(404);
        }

        $user = $moderationRequest->entityUser;
        if (!$user) {
            return back()->withErrors(['moderation' => 'Пользователь не найден.']);
        }

        if ($user->status?->value !== 'confirmed') {
            return back()->withErrors(['moderation' => 'Блокировать можно только подтвержденного пользователя.']);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $user->update([
            'status' => 'blocked',
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

        if ($moderationRequest->entity_type !== ModerationEntityType::User) {
            abort(404);
        }

        $user = $moderationRequest->entityUser;
        if (!$user) {
            return back()->withErrors(['moderation' => 'Пользователь не найден.']);
        }

        if ($user->status?->value !== 'blocked') {
            return back()->withErrors(['moderation' => 'Пользователь не заблокирован.']);
        }

        $user->update([
            'status' => 'confirmed',
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
