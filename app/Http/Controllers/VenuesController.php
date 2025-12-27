<?php

namespace App\Http\Controllers;

use App\Domain\Venues\Services\VenueCatalogService;
use Inertia\Inertia;

class VenuesController extends Controller
{
    public function index()
    {
        $catalog = app(VenueCatalogService::class);
        $navItems = $catalog->getNavigationItems();
        $catalogData = $catalog->getHallsList();

        return Inertia::render('Halls', [
            'appName' => config('app.name'),
            'halls' => $catalogData['halls'],
            'activeType' => $catalogData['activeType'],
            'activeTypeSlug' => $catalogData['activeTypeSlug'],
            'navigation' => [
                'title' => 'Площадки',
                'items' => $navItems,
            ],
        ]);
    }

    public function type(string $type)
    {
        $catalog = app(VenueCatalogService::class);
        $navItems = $catalog->getNavigationItems();
        $catalogData = $catalog->getHallsList($type);

        if (!$catalogData) {
            abort(404);
        }

        return Inertia::render('Halls', [
            'appName' => config('app.name'),
            'halls' => $catalogData['halls'],
            'activeType' => $catalogData['activeType'],
            'activeTypeSlug' => $catalogData['activeTypeSlug'],
            'navigation' => [
                'title' => 'Навигация',
                'items' => $navItems,
            ],
        ]);
    }
}
