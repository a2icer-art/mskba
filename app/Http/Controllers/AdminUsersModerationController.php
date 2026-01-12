<?php

namespace App\Http\Controllers;

use App\Domain\Moderation\Enums\ModerationEntityType;
use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Models\ModerationRequest;
use App\Domain\Permissions\Enums\PermissionScope;
use App\Domain\Permissions\Models\Permission;
use App\Domain\Users\Enums\UserConfirmedBy;
use App\Models\User;
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
            'user' => $request->user(),
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
                'entityUser.roles.permissions:id,code,label',
                'entityUser.permissions:id,code,label',
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
                $permissions = $this->resolveUserPermissions($user);
                $assignedPermissions = $this->resolveDirectPermissionCodes($user);

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
                    'permissions' => $permissions,
                    'assigned_permissions' => $assignedPermissions,
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
            'permissionGroups' => $this->getPermissionGroups(),
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

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,code'],
        ]);

        $permissionCodes = $this->filterApprovalPermissionCodes($data['permissions'] ?? []);
        $permissionIds = [];
        if ($permissionCodes !== []) {
            $permissionIds = Permission::query()
                ->whereIn('code', $permissionCodes)
                ->where('scope', PermissionScope::Global)
                ->pluck('id')
                ->all();
        }

        $user->permissions()->sync($permissionIds);

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

    public function updatePermissions(Request $request, ModerationRequest $moderationRequest)
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

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,code'],
        ]);

        $permissionCodes = $this->filterApprovalPermissionCodes($data['permissions'] ?? []);
        $permissionIds = [];
        if ($permissionCodes !== []) {
            $permissionIds = Permission::query()
                ->whereIn('code', $permissionCodes)
                ->where('scope', PermissionScope::Global)
                ->pluck('id')
                ->all();
        }

        $user->permissions()->sync($permissionIds);

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

    private function resolveUserPermissions(?User $user): array
    {
        if (!$user) {
            return [];
        }

        $rolePermissions = $user->roles
            ? $user->roles->flatMap(fn ($role) => $role->permissions ?? collect())
            : collect();
        $userPermissions = $user->permissions ?? collect();

        return $rolePermissions
            ->merge($userPermissions)
            ->unique('code')
            ->sortBy('label')
            ->values()
            ->map(static function ($permission): array {
                return [
                    'code' => $permission->code,
                    'label' => $permission->label ?: $permission->code,
                ];
            })
            ->all();
    }

    private function resolveDirectPermissionCodes(?User $user): array
    {
        if (!$user || !$user->permissions) {
            return [];
        }

        return $user->permissions
            ->pluck('code')
            ->all();
    }

    private function getPermissionGroups(): array
    {
        $titles = [
            'admin' => 'Система',
            'moderation' => 'Модерация',
            'logs' => 'Система',
            'venue' => 'Площадки',
            'event' => 'События',
            'comment' => 'Комментарии',
            'rating' => 'Рейтинги',
            'article' => 'Статьи',
            'article_category' => 'Категории статей',
        ];

        $permissions = Permission::query()
            ->where('scope', PermissionScope::Global)
            ->orderBy('label')
            ->get(['code', 'label']);

        $allowedPrefixes = $this->getApprovalPermissionPrefixes();
        $grouped = [];
        foreach ($permissions as $permission) {
            $prefix = explode('.', $permission->code, 2)[0];
            if (!in_array($prefix, $allowedPrefixes, true)) {
                continue;
            }
            $title = $titles[$prefix] ?? 'Прочее';
            $grouped[$title][] = [
                'code' => $permission->code,
                'label' => $permission->label ?: $permission->code,
            ];
        }

        $orderedGroups = [];
        foreach (array_unique(array_values($titles)) as $title) {
            if (!empty($grouped[$title])) {
                $orderedGroups[] = [
                    'title' => $title,
                    'items' => $grouped[$title],
                ];
            }
            unset($grouped[$title]);
        }

        foreach ($grouped as $title => $items) {
            $orderedGroups[] = [
                'title' => $title,
                'items' => $items,
            ];
        }

        return $orderedGroups;
    }

    private function getApprovalPermissionPrefixes(): array
    {
        return [
            'venue',
            'event',
            'comment',
            'rating',
        ];
    }

    private function filterApprovalPermissionCodes(array $permissionCodes): array
    {
        if ($permissionCodes === []) {
            return [];
        }

        $allowedPrefixes = $this->getApprovalPermissionPrefixes();

        return array_values(array_filter(
            $permissionCodes,
            static function (string $code) use ($allowedPrefixes): bool {
                $prefix = explode('.', $code, 2)[0];

                return in_array($prefix, $allowedPrefixes, true);
            }
        ));
    }
}
