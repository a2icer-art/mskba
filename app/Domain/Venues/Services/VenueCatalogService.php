<?php

namespace App\Domain\Venues\Services;

use App\Domain\Venues\Models\Venue;
use App\Domain\Venues\Models\VenueType;
use App\Domain\Metros\Models\Metro;
use App\Support\DateFormatter;
use App\Models\User;
use Illuminate\Support\Str;

class VenueCatalogService
{
    public function getTypeOptions(): array
    {
        return VenueType::query()
            ->orderBy('name')
            ->get(['id', 'name', 'alias'])
            ->map(fn (VenueType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'alias' => $type->alias,
            ])
            ->values()
            ->all();
    }

    public function getMetroOptions(): array
    {
        return Metro::query()
            ->where('status', 1)
            ->orderBy('city')
            ->orderBy('line_name')
            ->orderBy('name')
            ->get(['id', 'name', 'line_name', 'line_color', 'city'])
            ->map(fn (Metro $metro) => [
                'id' => $metro->id,
                'name' => $metro->name,
                'line_name' => $metro->line_name,
                'line_color' => $metro->line_color,
                'city' => $metro->city,
            ])
            ->values()
            ->all();
    }

    public function getHallsList(?string $typeSlug = null, ?User $user = null): ?array
    {
        if (!$typeSlug) {
            return [
                'venues' => $this->getHalls($user),
                'activeType' => null,
                'activeTypeSlug' => null,
            ];
        }

        $venueType = $this->resolveType($typeSlug);
        if (!$venueType) {
            return null;
        }

        return [
            'venues' => $this->getHalls($user),
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

    private function getHalls(?User $user): array
    {
        $query = Venue::query()
            ->with(['venueType:id,name,alias', 'latestAddress.metro:id,name,line_name,line_color,city'])
            ->orderBy('name');

        $query->visibleFor($user);

        return $query->get(['id', 'name', 'alias', 'venue_type_id', 'created_at', 'status', 'created_by'])
            ->map(fn (Venue $venue) => [
                'id' => $venue->id,
                'name' => $venue->name,
                'alias' => $venue->alias,
                'status' => $venue->status?->value,
                'address' => $venue->latestAddress?->display_address,
                'metro' => $venue->latestAddress?->metro?->only(['id', 'name', 'line_name', 'line_color', 'city']),
                'created_at' => DateFormatter::dateTime($venue->created_at),
                'type' => $venue->venueType?->only(['id', 'name', 'alias']),
                'type_slug' => $venue->venueType?->alias ? Str::plural($venue->venueType->alias) : null,
            ])
            ->values()
            ->all();
    }
}
