<?php

namespace App\Http\Middleware;

use App\Domain\Participants\Enums\ParticipantRoleStatus;
use App\Domain\Participants\Models\ParticipantRole;
use App\Domain\Permissions\Models\Permission;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Messages\Services\MessageCountersService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $userRoles = $user ? $user->roles()->pluck('alias')->all() : [];
        $userRoleLevel = $user ? (int) $user->roles()->max('level') : 0;
        $permissionCodes = [];
        if ($user) {
            $roleIds = $user->roles()->pluck('roles.id')->all();
            $rolePermissions = Permission::query()
                ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $roleIds))
                ->pluck('code')
                ->all();
            $userPermissions = $user->permissions()->pluck('code')->all();
            $permissionCodes = array_values(array_unique(array_merge($rolePermissions, $userPermissions)));
            if ($user->status?->value !== UserStatus::Confirmed->value) {
                $permissionCodes = [];
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user
                    ? [
                        'id' => $user->id,
                        'login' => $user->login,
                        'status' => $user->status?->value,
                        'roles' => $userRoles,
                        'role_level' => $userRoleLevel,
                        'permissions' => $permissionCodes,
                    ]
                    : null,
            ],
            'messageCounters' => $user
                ? [
                    'unread_messages' => app(MessageCountersService::class)->getUnreadMessages($user),
                ]
                : [
                    'unread_messages' => 0,
                ],
            'participantRoles' => ParticipantRole::query()
                ->where('status', ParticipantRoleStatus::Confirmed)
                ->orderBy('sort')
                ->get(['id', 'name', 'alias']),
        ];
    }
}
