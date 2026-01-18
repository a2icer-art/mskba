<?php

namespace App\Domain\Venues\Services;

use App\Domain\Venues\Models\Venue;
use App\Domain\Venues\Models\VenueType;
use App\Domain\Metros\Models\Metro;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Contracts\Enums\ContractStatus;
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

        $venues = $query->get(['id', 'name', 'alias', 'venue_type_id', 'created_at', 'status', 'created_by']);
        $myVenueIds = $this->resolveMyVenueIds($user, $venues);
        $myVenueLookup = array_fill_keys($myVenueIds, true);

        return $venues
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
                'is_my_venue' => (bool) ($myVenueLookup[$venue->id] ?? false),
            ])
            ->values()
            ->all();
    }

    private function resolveMyVenueIds(?User $user, $venues): array
    {
        if (!$user) {
            return [];
        }

        $now = now();
        $entityType = (new Venue())->getMorphClass();

        $contractVenueIds = Contract::query()
            ->where('user_id', $user->id)
            ->where('entity_type', $entityType)
            ->where('status', ContractStatus::Active->value)
            ->where(function ($dateQuery) use ($now): void {
                $dateQuery->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($dateQuery) use ($now): void {
                $dateQuery->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->pluck('entity_id')
            ->all();

        $createdVenueIds = $venues
            ->where('created_by', $user->id)
            ->pluck('id')
            ->all();

        return array_values(array_unique(array_merge($createdVenueIds, $contractVenueIds)));
    }
}
