<?php

namespace App\Http\Controllers;

use App\Domain\Permissions\Enums\PermissionCode;
use App\Domain\Permissions\Services\PermissionChecker;
use App\Domain\Users\Enums\UserConfirmedBy;
use App\Domain\Users\Enums\UserRegisteredVia;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Models\Role;
use App\Domain\Users\Models\UserContact;
use App\Domain\Users\Models\ContactVerification;
use App\Domain\Participants\Enums\ParticipantRoleAssignmentStatus;
use App\Domain\Participants\Enums\ParticipantRoleStatus;
use App\Domain\Participants\Models\ParticipantRole;
use App\Domain\Participants\Models\ParticipantRoleAssignment;
use App\Domain\Participants\Services\ParticipantRoleProfileFactory;
use App\Models\User;
use App\Presentation\Breadcrumbs\AdminBreadcrumbsPresenter;
use App\Presentation\Navigation\AdminNavigationPresenter;
use App\Support\DateFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminUsersController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureAccess($request);

        $query = User::query()
            ->with([
                'roles:id,name,alias',
                'contacts' => fn ($builder) => $builder->orderBy('id'),
                'participantRoleAssignments.role:id,name,alias',
            ])
            ->orderByDesc('id');

        $search = $request->string('q')->toString();
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('login', 'like', '%' . $search . '%');
                if (is_numeric($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $status = $request->string('status')->toString();
        $allowedStatuses = array_map(
            static fn (UserStatus $item) => $item->value,
            UserStatus::cases()
        );
        if (!in_array($status, $allowedStatuses, true)) {
            $status = '';
        }
        if ($status !== '') {
            $query->where('status', $status);
        }

        $role = $request->string('role')->toString();
        if ($role !== '') {
            $query->whereHas('roles', fn ($builder) => $builder->where('alias', $role));
        }

        $registeredVia = $request->string('registered_via')->toString();
        $allowedRegistered = array_map(
            static fn (UserRegisteredVia $item) => $item->value,
            UserRegisteredVia::cases()
        );
        if (!in_array($registeredVia, $allowedRegistered, true)) {
            $registeredVia = '';
        }
        if ($registeredVia !== '') {
            $query->where('registered_via', $registeredVia);
        }

        $registeredFrom = $request->string('registered_from')->toString();
        if ($registeredFrom !== '') {
            $query->whereDate('created_at', '>=', $registeredFrom);
        }

        $registeredTo = $request->string('registered_to')->toString();
        if ($registeredTo !== '') {
            $query->whereDate('created_at', '<=', $registeredTo);
        }

        $usersPaginator = $query
            ->paginate(15)
            ->withQueryString();

        $blockedByIds = $usersPaginator->pluck('blocked_by')
            ->filter()
            ->unique()
            ->values();
        $blockedByLogins = $blockedByIds->isEmpty()
            ? []
            : User::query()
                ->whereIn('id', $blockedByIds)
                ->pluck('login', 'id')
                ->all();

        $users = $usersPaginator->through(function (User $user) use ($blockedByLogins) {
            $status = $user->status?->value ?? UserStatus::Unconfirmed->value;
            $statusChangedAt = null;
            $statusChangedBy = null;

            if ($status === UserStatus::Blocked->value) {
                $statusChangedAt = DateFormatter::dateTime($user->blocked_at);
                $statusChangedBy = $blockedByLogins[$user->blocked_by] ?? null;
            } elseif ($status === UserStatus::Confirmed->value) {
                $statusChangedAt = DateFormatter::dateTime($user->confirmed_at);
                $statusChangedBy = $this->formatConfirmedBy($user->confirmed_by);
            }

            return [
                'id' => $user->id,
                'login' => $user->login,
                'roles' => $user->roles
                    ->map(fn ($role) => [
                        'name' => $role->name,
                        'alias' => $role->alias,
                    ])
                    ->values()
                    ->all(),
                'participant_roles' => $user->participantRoleAssignments
                    ->where('status', ParticipantRoleAssignmentStatus::Confirmed)
                    ->map(fn ($assignment) => [
                        'id' => $assignment->id,
                        'name' => $assignment->role?->name,
                        'alias' => $assignment->role?->alias,
                    ])
                    ->values()
                    ->all(),
                'status' => $status,
                'status_changed_at' => $statusChangedAt,
                'status_changed_by' => $statusChangedBy,
                'registered_at' => DateFormatter::dateTime($user->created_at),
                'registered_via' => $user->registered_via?->value,
                'registered_via_label' => $this->formatRegisteredVia($user->registered_via),
                'contacts' => $user->contacts
                    ->map(fn (UserContact $contact) => [
                        'id' => $contact->id,
                        'type' => $contact->type?->value,
                        'value' => $contact->value,
                        'confirmed_at' => DateFormatter::dateTime($contact->confirmed_at),
                    ])
                    ->values()
                    ->all(),
            ];
        });

        $navigation = app(AdminNavigationPresenter::class)->present([
            'user' => $request->user(),
        ]);
        $breadcrumbs = app(AdminBreadcrumbsPresenter::class)->present([
            'user' => $request->user(),
            'currentHref' => '/admin/users',
        ])['data'];

        return Inertia::render('Admin/Users', [
            'appName' => config('app.name'),
            'navigation' => $navigation,
            'activeHref' => '/admin/users',
            'breadcrumbs' => $breadcrumbs,
            'users' => $users,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'role' => $role,
                'registered_via' => $registeredVia,
                'registered_from' => $registeredFrom,
                'registered_to' => $registeredTo,
            ],
            'statusOptions' => [
                ['value' => '', 'label' => 'Все'],
                ['value' => UserStatus::Unconfirmed->value, 'label' => 'Не подтвержден'],
                ['value' => UserStatus::Confirmed->value, 'label' => 'Подтвержден'],
                ['value' => UserStatus::Blocked->value, 'label' => 'Заблокирован'],
            ],
            'roleOptions' => $this->resolveRoleOptions(),
            'participantRoleOptions' => $this->resolveParticipantRoleOptions(),
            'registeredViaOptions' => $this->resolveRegisteredViaOptions(),
        ]);
    }

    public function resetContactConfirmation(Request $request, User $user, UserContact $contact)
    {
        $this->ensureAccess($request);

        if ($contact->user_id !== $user->id) {
            abort(404);
        }

        if ($contact->confirmed_at === null) {
            return back()->withErrors([
                'contact' => 'Контакт не подтвержден.',
            ]);
        }

        ContactVerification::query()
            ->where('contact_id', $contact->id)
            ->forceDelete();

        $contact->update([
            'confirmed_at' => null,
            'updated_by' => $request->user()?->id,
        ]);

        return back()->with('notice', 'Подтверждение контакта сброшено.');
    }

    public function updateRoles(Request $request, User $user)
    {
        $this->ensureAccess($request);

        $data = $request->validate([
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'alias')],
        ]);

        $roles = $data['roles'] ?? [];
        if (!in_array('user', $roles, true)) {
            $roles[] = 'user';
        }

        $roleIds = Role::query()
            ->whereIn('alias', $roles)
            ->pluck('id')
            ->all();

        $user->roles()->sync($roleIds);

        return back()->with('notice', 'Роли пользователя обновлены.');
    }

    public function updateParticipantRoles(
        Request $request,
        User $user,
        ParticipantRoleProfileFactory $profileFactory
    ) {
        $this->ensureAccess($request);

        $data = $request->validate([
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('participant_roles', 'alias')],
        ]);

        $aliases = array_values(array_unique($data['roles'] ?? []));

        $roles = ParticipantRole::query()
            ->whereIn('alias', $aliases)
            ->get(['id', 'alias']);

        $roleIdsByAlias = $roles->mapWithKeys(fn ($role) => [$role->alias => $role->id]);

        $existing = ParticipantRoleAssignment::query()
            ->where('user_id', $user->id)
            ->whereNull('context_type')
            ->whereNull('context_id')
            ->get();

        $existingByAlias = $existing->mapWithKeys(function (ParticipantRoleAssignment $assignment) {
            return [$assignment->role?->alias => $assignment];
        });

        DB::transaction(function () use (
            $aliases,
            $roleIdsByAlias,
            $existingByAlias,
            $user,
            $profileFactory
        ) {
            foreach ($aliases as $alias) {
                if ($alias === '' || $existingByAlias->has($alias)) {
                    continue;
                }

                $roleId = $roleIdsByAlias[$alias] ?? null;
                if (!$roleId) {
                    continue;
                }

                $assignment = ParticipantRoleAssignment::query()->create([
                    'user_id' => $user->id,
                    'participant_role_id' => $roleId,
                    'context_type' => null,
                    'context_id' => null,
                    'status' => ParticipantRoleAssignmentStatus::Confirmed,
                    'created_by' => $request->user()?->id,
                    'updated_by' => $request->user()?->id,
                    'confirmed_at' => now(),
                    'confirmed_by' => $request->user()?->id,
                    'deleted_by' => null,
                ]);

                $profileFactory->createForAlias($alias, $assignment->id, $request->user()?->id ?? $user->id);
            }

            foreach ($existingByAlias as $alias => $assignment) {
                if (!in_array($alias, $aliases, true)) {
                    $profileFactory->deleteForAlias($alias, $assignment->id, $request->user()?->id ?? $user->id);
                    $assignment->update([
                        'deleted_by' => $request->user()?->id,
                    ]);
                    $assignment->delete();
                }
            }
        });

        return back()->with('notice', 'Роли участника обновлены.');
    }

    private function ensureAccess(Request $request): void
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $hasAccess = app(PermissionChecker::class)->can($user, PermissionCode::AdminAccess);
        if (!$hasAccess) {
            abort(403);
        }
    }

    private function formatConfirmedBy(?UserConfirmedBy $confirmedBy): ?string
    {
        return match ($confirmedBy) {
            UserConfirmedBy::Admin => 'admin',
            UserConfirmedBy::Email => 'email',
            UserConfirmedBy::Phone => 'phone',
            UserConfirmedBy::Telegram => 'telegram',
            UserConfirmedBy::Vk => 'vk',
            UserConfirmedBy::Other => 'other',
            default => null,
        };
    }

    private function formatRegisteredVia(?UserRegisteredVia $registeredVia): ?string
    {
        return match ($registeredVia) {
            UserRegisteredVia::Site => 'Сайт',
            UserRegisteredVia::TgLink => 'Telegram',
            UserRegisteredVia::EmailLink => 'Email',
            UserRegisteredVia::Other => 'Другое',
            default => null,
        };
    }

    private function resolveRegisteredViaOptions(): array
    {
        return [
            ['value' => '', 'label' => 'Все'],
            ['value' => UserRegisteredVia::Site->value, 'label' => 'Сайт'],
            ['value' => UserRegisteredVia::TgLink->value, 'label' => 'Telegram'],
            ['value' => UserRegisteredVia::EmailLink->value, 'label' => 'Email'],
            ['value' => UserRegisteredVia::Other->value, 'label' => 'Другое'],
        ];
    }

    private function resolveRoleOptions(): array
    {
        return Role::query()
            ->orderBy('level', 'desc')
            ->get(['name', 'alias'])
            ->map(fn ($role) => [
                'value' => $role->alias,
                'label' => $role->name,
            ])
            ->prepend(['value' => '', 'label' => 'Все'])
            ->values()
            ->all();
    }

    private function resolveParticipantRoleOptions(): array
    {
        return ParticipantRole::query()
            ->where('status', ParticipantRoleStatus::Confirmed)
            ->orderBy('sort')
            ->get(['name', 'alias'])
            ->map(fn ($role) => [
                'value' => $role->alias,
                'label' => $role->name,
            ])
            ->values()
            ->all();
    }
}
