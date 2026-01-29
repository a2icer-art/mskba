<?php

namespace App\Presentation\Navigation;

use App\Domain\Venues\Models\VenueType;
use Illuminate\Support\Str;

class VenueNavigationPresenter extends NavigationPresenter
{
    protected function resolveTitle(array $ctx): string
    {
        return $ctx['title'] ?? 'Площадки';
    }

    protected function buildItems(array $ctx): array
    {
        $items = [
            [
                'label' => 'Все площадки',
                'href' => '/venues',
            ],
        ];

        $types = VenueType::query()
            ->forNavigation()
            ->get();

        $items = array_merge($items, $types
            ->map(fn (VenueType $type) => [
                'label' => $type->plural_name ?: $type->name,
                'href' => '/venues/' . Str::plural($type->alias),
            ])
            ->values()
            ->all());

        return $items;
    }
}
