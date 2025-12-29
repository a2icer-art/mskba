<?php

namespace App\Domain\Venues\Services;

use App\Domain\Venues\Models\Venue;
use App\Domain\Venues\Models\VenueType;
use App\Domain\Venues\Enums\VenueStatus;
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

    public function getHallsList(?string $typeSlug = null, ?int $userId = null, int $roleLevel = 0): ?array
    {
        if (!$typeSlug) {
            return [
                'halls' => $this->getHalls($userId, $roleLevel),
                'activeType' => null,
                'activeTypeSlug' => null,
            ];
        }

        $venueType = $this->resolveType($typeSlug);
        if (!$venueType) {
            return null;
        }

        return [
            'halls' => $this->getHalls($userId, $roleLevel),
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

    private function getHalls(?int $userId, int $roleLevel): array
    {
        $query = Venue::query()
            ->with(['venueType:id,name,alias', 'latestAddress'])
            ->orderBy('name');

        if ($roleLevel <= 20) {
            $query->where(function ($builder) use ($userId) {
                $builder->where('status', VenueStatus::Confirmed->value);

                if ($userId) {
                    $builder->orWhere('created_by', $userId);
                }
            });
        }

        return $query->get(['id', 'name', 'alias', 'venue_type_id', 'created_at', 'status', 'created_by'])
            ->map(fn (Venue $venue) => [
                'id' => $venue->id,
                'name' => $venue->name,
                'alias' => $venue->alias,
                'status' => $venue->status?->value,
                'address' => $venue->latestAddress?->display_address,
                'created_at' => DateFormatter::dateTime($venue->created_at),
                'type' => $venue->venueType?->only(['id', 'name', 'alias']),
                'type_slug' => $venue->venueType?->alias ? Str::plural($venue->venueType->alias) : null,
            ])
            ->values()
            ->all();
    }
}
