<?php

namespace App\Http\Controllers;

use App\Domain\Admin\Services\EventDefaultsService;
use App\Presentation\Breadcrumbs\AdminBreadcrumbsPresenter;
use App\Presentation\Navigation\AdminNavigationPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminSettingsController extends Controller
{
    public function index(Request $request, EventDefaultsService $defaultsService)
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 20);

        $defaults = $defaultsService->get();
        $navigation = app(AdminNavigationPresenter::class)->present([
            'user' => $request->user(),
        ]);
        $breadcrumbs = app(AdminBreadcrumbsPresenter::class)->present([
            'user' => $request->user(),
            'currentHref' => '/admin/settings',
        ])['data'];

        return Inertia::render('Admin/Settings', [
            'appName' => config('app.name'),
            'navigation' => $navigation,
            'activeHref' => '/admin/settings',
            'breadcrumbs' => $breadcrumbs,
            'defaults' => $defaults,
        ]);
    }

    public function update(Request $request, EventDefaultsService $defaultsService)
    {
        $roleLevel = $this->getRoleLevel($request);
        $this->ensureAccess($roleLevel, 20);

        $data = $request->validate([
            'lead_time_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'min_duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ], [
            'lead_time_minutes.required' => 'Укажите допустимое время до начала события.',
            'lead_time_minutes.integer' => 'Допустимое время до начала события должно быть числом.',
            'lead_time_minutes.min' => 'Допустимое время до начала события не может быть отрицательным.',
            'lead_time_minutes.max' => 'Допустимое время до начала события не может превышать 1440 минут.',
            'min_duration_minutes.required' => 'Укажите минимальную длительность события.',
            'min_duration_minutes.integer' => 'Минимальная длительность события должна быть числом.',
            'min_duration_minutes.min' => 'Минимальная длительность события не может быть меньше 1 минуты.',
            'min_duration_minutes.max' => 'Минимальная длительность события не может превышать 1440 минут.',
        ]);

        $defaultsService->update($data);

        return back()->with('notice', 'Настройки событий обновлены.');
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