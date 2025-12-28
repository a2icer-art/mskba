<?php

namespace App\Http\Controllers;

use App\Domain\Filament\Services\FilamentNavigationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FilamentController extends Controller
{
    public function index(Request $request)
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 10);

        $navigation = app(FilamentNavigationService::class);
        $items = $navigation->getMenuItems($roleLevel);

        return Inertia::render('Filament/Index', [
            'appName' => config('app.name'),
            'navigation' => [
                'title' => 'Разделы',
                'items' => $items,
            ],
            'activeHref' => '',
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
}
