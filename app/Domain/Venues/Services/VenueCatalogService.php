<?php

namespace App\Domain\Venues\Services;

use App\Domain\Venues\Models\Venue;
use App\Domain\Venues\Models\VenueType;
use App\Support\DateFormatter;
use Illuminate\Support\Str;

class VenueCatalogService
{
    public function getNavigationItems(): array
    {
        return VenueType::query()
            ->orderBy('name')
            ->get(['name', 'plural_name', 'alias'])
            ->map(fn (VenueType $type) => [
                'label' => $type->plural_name ?: $type->name,
                'href' => '/venues/' . Str::plural($type->alias),
            ])
            ->values()
            ->all();
    }

    public function getHallsList(?string $typeSlug = null): ?array
    {
        if (!$typeSlug) {
            return [
                'halls' => $this->getHalls(),
                'activeType' => null,
                'activeTypeSlug' => null,
            ];
        }

        $venueType = $this->resolveType($typeSlug);
        if (!$venueType) {
            return null;
        }

        return [
            'halls' => $this->getHalls(),
            'activeType' => $venueType->alias,
            'activeTypeSlug' => Str::plural($venueType->alias),
        ];
    }

    private function resolveType(string $typeSlug): ?VenueType
    {
        $venueType = VenueType::query()
            ->where('alias', $typeSlug)
            ->first();

        if ($venueType) {
            return $venueType;
        }

        $singular = Str::singular($typeSlug);
        if ($singular === $typeSlug) {
            return null;
        }

        return VenueType::query()
            ->where('alias', $singular)
            ->first();
    }

    private function getHalls(): array
    {
        return Venue::query()
            ->with(['venueType:id,name,alias'])
            ->orderBy('name')
            ->get(['id', 'name', 'alias', 'venue_type_id', 'address', 'created_at'])
            ->map(fn (Venue $venue) => [
                'id' => $venue->id,
                'name' => $venue->name,
                'alias' => $venue->alias,
                'address' => $venue->address,
                'created_at' => DateFormatter::dateTime($venue->created_at),
                'type' => $venue->venueType?->only(['id', 'name', 'alias']),
            ])
            ->values()
            ->all();
    }
}
