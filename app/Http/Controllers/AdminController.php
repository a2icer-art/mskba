<?php

namespace App\Http\Controllers;

use App\Presentation\Navigation\AdminNavigationPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 10);

        $navigation = app(AdminNavigationPresenter::class)->present([
            'user' => $request->user(),
        ]);

        return Inertia::render('Admin/Index', [
            'appName' => config('app.name'),
            'navigation' => $navigation,
            'activeHref' => $this->resolveActiveHref($navigation),
        ]);
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

    private function resolveActiveHref(array $navigation): string
    {
        $data = $navigation['data'] ?? [];
        if (!is_array($data) || $data === []) {
            return '';
        }

        $first = $data[0] ?? [];
        if (is_array($first) && isset($first['href'])) {
            return (string) $first['href'];
        }

        if (is_array($first) && isset($first['items'][0]['href'])) {
            return (string) $first['items'][0]['href'];
        }

        return '';
    }
}
