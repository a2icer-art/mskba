<?php

namespace App\Http\Controllers;

use App\Domain\Admin\Services\SiteAssetsService;
use App\Domain\Seo\Services\PageMetaCatalogService;
use App\Domain\Seo\Services\PageMetaService;
use App\Presentation\Breadcrumbs\AdminBreadcrumbsPresenter;
use App\Presentation\Navigation\AdminNavigationPresenter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminSeoController extends Controller
{
    public function index(Request $request, PageMetaCatalogService $catalog, SiteAssetsService $assets)
    {
        $navigation = app(AdminNavigationPresenter::class)->present([
            'user' => $request->user(),
        ]);
        $breadcrumbs = app(AdminBreadcrumbsPresenter::class)->present([
            'user' => $request->user(),
            'currentHref' => '/admin/seo',
        ])['data'];

        return Inertia::render('Admin/Seo', [
            'appName' => config('app.name'),
            'navigation' => $navigation,
            'activeHref' => '/admin/seo',
            'breadcrumbs' => $breadcrumbs,
            'groups' => $catalog->getGroups(),
            'assets' => $assets->get(),
        ]);
    }

    public function update(Request $request, PageMetaService $service, PageMetaCatalogService $catalog)
    {
        $data = $request->validate([
            'page_type' => ['required', 'string', 'max:120'],
            'page_id' => ['required', 'integer', 'min:0'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'keywords' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->ensureAllowed($data['page_type'], (int) $data['page_id'], $catalog);

        $service->upsert($data, $request->user());

        return back()->with('notice', 'Метатеги сохранены.');
    }

    public function bulkUpdate(Request $request, PageMetaService $service, PageMetaCatalogService $catalog)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.page_type' => ['required', 'string', 'max:120'],
            'items.*.page_id' => ['required', 'integer', 'min:0'],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.keywords' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach ($data['items'] as $item) {
            $this->ensureAllowed($item['page_type'], (int) $item['page_id'], $catalog);
            $service->upsert($item, $request->user());
        }

        return back()->with('notice', 'Метатеги сохранены.');
    }

    public function uploadFavicon(Request $request, SiteAssetsService $assets)
    {
        $data = $request->validate([
            'favicon' => ['required', 'file', 'mimes:ico,png,svg', 'max:2048'],
        ], [
            'favicon.required' => 'Выберите файл favicon.',
            'favicon.mimes' => 'Поддерживаются форматы ico, png, svg.',
            'favicon.max' => 'Размер favicon не должен превышать 2 МБ.',
        ]);

        $assets->updateFavicon($data['favicon']);

        return back()->with('notice', 'Favicon обновлен.');
    }

    public function updateMetaSettings(Request $request, SiteAssetsService $assets)
    {
        $data = $request->validate([
            'include_site_title' => ['nullable', 'boolean'],
        ]);

        $assets->updateMetaSettings($data);

        return back()->with('notice', 'Настройки обновлены.');
    }

    private function ensureAllowed(string $pageType, int $pageId, PageMetaCatalogService $catalog): void
    {
        $key = "{$pageType}:{$pageId}";
        if (!in_array($key, $catalog->getAllowedPairs(), true)) {
            throw ValidationException::withMessages([
                'page_type' => 'Недоступная страница для редактирования.',
            ]);
        }
    }
}
