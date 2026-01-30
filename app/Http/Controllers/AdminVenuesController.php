<?php

namespace App\Http\Controllers;

use App\Domain\Venues\Models\Amenity;
use App\Domain\Venues\Services\AmenityIconService;
use App\Presentation\Breadcrumbs\AdminBreadcrumbsPresenter;
use App\Presentation\Navigation\AdminNavigationPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminVenuesController extends Controller
{
    public function index(Request $request, AmenityIconService $icons)
    {
        $navigation = app(AdminNavigationPresenter::class)->present([
            'user' => $request->user(),
        ]);
        $breadcrumbs = app(AdminBreadcrumbsPresenter::class)->present([
            'user' => $request->user(),
            'currentHref' => '/admin/venues',
        ])['data'];

        $amenities = Amenity::query()
            ->where('is_custom', false)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'alias', 'icon_path', 'sort_order'])
            ->map(fn (Amenity $amenity) => [
                'id' => $amenity->id,
                'name' => $amenity->name,
                'alias' => $amenity->alias,
                'icon_url' => $icons->getUrl($amenity->icon_path),
                'sort_order' => $amenity->sort_order ?? 0,
            ])
            ->all();

        return Inertia::render('Admin/Venues', [
            'appName' => config('app.name'),
            'navigation' => $navigation,
            'activeHref' => '/admin/venues',
            'breadcrumbs' => $breadcrumbs,
            'amenities' => $amenities,
        ]);
    }

    public function uploadAmenityIcon(Request $request, Amenity $amenity, AmenityIconService $icons)
    {
        if ($amenity->is_custom) {
            abort(403);
        }

        $data = $request->validate([
            'icon' => ['required', 'file', 'mimes:svg,png,jpg,jpeg,webp', 'max:2048'],
        ], [
            'icon.required' => 'Выберите файл иконки.',
            'icon.mimes' => 'Поддерживаются форматы svg, png, jpg, webp.',
            'icon.max' => 'Размер иконки не должен превышать 2 МБ.',
        ]);

        $icons->upload($amenity, $data['icon'], $request->user());

        return back()->with('notice', 'Иконка обновлена.');
    }

    public function storeAmenity(Request $request, AmenityIconService $icons)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('amenities', 'name')
                ->where(fn ($query) => $query->where('is_custom', false)->whereNull('venue_id')->whereNull('deleted_at'))],
            'alias' => ['nullable', 'string', 'max:120', Rule::unique('amenities', 'alias')
                ->where(fn ($query) => $query->where('is_custom', false)->whereNull('venue_id')->whereNull('deleted_at'))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'icon' => ['nullable', 'file', 'mimes:svg,png,jpg,jpeg,webp', 'max:2048'],
        ], [
            'name.required' => 'Введите название опции.',
            'name.max' => 'Название опции не должно превышать 120 символов.',
            'name.unique' => 'Опция с таким названием уже существует.',
            'alias.unique' => 'Опция с таким alias уже существует.',
            'sort_order.integer' => 'Сортировка должна быть числом.',
            'icon.mimes' => 'Поддерживаются форматы svg, png, jpg, webp.',
            'icon.max' => 'Размер иконки не должен превышать 2 МБ.',
        ]);

        $alias = $data['alias'] ?? '';
        $alias = $alias !== '' ? Str::slug($alias) : Str::slug($data['name']);
        if ($alias === '') {
            $alias = Str::random(8);
        }

        $amenity = Amenity::query()->create([
            'name' => $data['name'],
            'alias' => $alias,
            'is_custom' => false,
            'venue_id' => null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => true,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        if (!empty($data['icon'])) {
            $icons->upload($amenity, $data['icon'], $request->user());
        }

        return back()->with('notice', 'Опция добавлена.');
    }

    public function updateAmenity(Request $request, Amenity $amenity)
    {
        if ($amenity->is_custom) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('amenities', 'name')
                ->ignore($amenity->id)
                ->where(fn ($query) => $query->where('is_custom', false)->whereNull('venue_id')->whereNull('deleted_at'))],
            'alias' => ['nullable', 'string', 'max:120', Rule::unique('amenities', 'alias')
                ->ignore($amenity->id)
                ->where(fn ($query) => $query->where('is_custom', false)->whereNull('venue_id')->whereNull('deleted_at'))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'name.required' => 'Введите название опции.',
            'name.max' => 'Название опции не должно превышать 120 символов.',
            'name.unique' => 'Опция с таким названием уже существует.',
            'alias.unique' => 'Опция с таким alias уже существует.',
            'sort_order.integer' => 'Сортировка должна быть числом.',
        ]);

        $alias = $data['alias'] ?? '';
        $alias = $alias !== '' ? Str::slug($alias) : Str::slug($data['name']);
        if ($alias === '') {
            $alias = $amenity->alias ?: Str::random(8);
        }

        $amenity->update([
            'name' => $data['name'],
            'alias' => $alias,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'updated_by' => $request->user()?->id,
        ]);

        return back()->with('notice', 'Опция обновлена.');
    }

    public function destroyAmenity(Request $request, Amenity $amenity)
    {
        if ($amenity->is_custom) {
            abort(403);
        }

        $amenity->update([
            'deleted_by' => $request->user()?->id,
        ]);
        $amenity->delete();

        $amenity->venues()->updateExistingPivot($amenity->venues->pluck('id')->all(), [
            'deleted_by' => $request->user()?->id,
            'deleted_at' => now(),
        ]);

        return back()->with('notice', 'Опция удалена.');
    }
}
