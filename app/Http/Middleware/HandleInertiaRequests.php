<?php

namespace App\Http\Middleware;

use App\Domain\Participants\Enums\ParticipantRoleStatus;
use App\Domain\Participants\Models\ParticipantRole;
use App\Domain\Admin\Services\SiteAssetsService;
use App\Domain\Permissions\Models\Permission;
use App\Domain\Seo\Services\PageMetaService;
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
            'meta' => $this->resolveMeta($request),
            'faviconUrl' => $this->resolveFaviconUrl(),
            'metaSettings' => $this->resolveMetaSettings(),
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
            'flash' => [
                'notice' => session('notice'),
                'error' => session('error'),
                'info' => session('info'),
                'telegram_verification' => session('telegram_verification'),
            ],
        ];
    }

    private function resolveFaviconUrl(): string
    {
        $assets = app(SiteAssetsService::class)->get();
        return (string) ($assets['favicon_url'] ?? '');
    }

    private function resolveMetaSettings(): array
    {
        return app(SiteAssetsService::class)->getMetaSettings();
    }

    private function resolveMeta(Request $request): ?array
    {
        $payload = $this->resolveMetaPayload($request);
        if (!$payload) {
            return null;
        }

        return app(PageMetaService::class)->resolve($payload['page_type'], $payload['page_id']);
    }

    private function resolveMetaPayload(Request $request): ?array
    {
        $route = $request->route();
        if (!$route) {
            return null;
        }

        $routeName = (string) ($route->getName() ?? '');
        $pageType = null;
        $pageId = 0;

        switch ($routeName) {
            case 'home':
                $pageType = 'page.home';
                break;
            case 'venues':
                $pageType = 'page.venues.index';
                break;
            case 'events.index':
                $pageType = 'page.events.index';
                break;
            case 'venues.show':
                $pageType = 'venue.show';
                $venue = $route->parameter('venue');
                $pageId = is_object($venue) ? (int) $venue->id : (int) $venue;
                break;
            case 'events.show':
                $pageType = 'event.show';
                $event = $route->parameter('event');
                $pageId = is_object($event) ? (int) $event->id : (int) $event;
                break;
            default:
                return null;
        }

        return [
            'page_type' => $pageType,
            'page_id' => $pageId,
        ];
    }
}
