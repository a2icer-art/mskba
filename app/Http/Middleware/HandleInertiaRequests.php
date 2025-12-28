<?php

namespace App\Http\Middleware;

use App\Domain\Participants\Enums\ParticipantRoleStatus;
use App\Domain\Participants\Models\ParticipantRole;
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

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user
                    ? [
                        'id' => $user->id,
                        'login' => $user->login,
                        'roles' => $userRoles,
                        'role_level' => $userRoleLevel,
                    ]
                    : null,
            ],
            'participantRoles' => ParticipantRole::query()
                ->where('status', ParticipantRoleStatus::Confirmed)
                ->orderBy('sort')
                ->get(['id', 'name', 'alias']),
        ];
    }
}
