<?php

namespace App\Domain\Venues\Services;

use App\Domain\Venues\Models\Venue;
use App\Domain\Media\Services\MediaService;
use App\Domain\Venues\Models\VenueType;
use App\Domain\Metros\Models\Metro;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Support\DateFormatter;
use App\Domain\Venues\Models\VenueSettings;
use App\Domain\Venues\Services\AmenityIconService;
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

    public function getHallsList(?string $typeSlug = null, ?User $user = null, ?string $avatarPlaceholderUrl = null): ?array
    {
        if (!$typeSlug) {
            return [
                'venues' => $this->getHalls($user, $avatarPlaceholderUrl),
                'activeType' => null,
                'activeTypeSlug' => null,
            ];
        }

        $venueType = $this->resolveType($typeSlug);
        if (!$venueType) {
            return null;
        }

        return [
            'venues' => $this->getHalls($user, $avatarPlaceholderUrl),
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

    private function getHalls(?User $user, ?string $avatarPlaceholderUrl = null): array
    {
        $mediaService = app(MediaService::class);
        $amenityIcons = app(AmenityIconService::class);
        $query = Venue::query()
            ->with([
                'venueType:id,name,alias',
                'latestAddress.metro:id,name,line_name,line_color,city',
                'settings',
                'amenities' => function ($query) {
                    $query->where('is_active', true)
                        ->select(['amenities.id', 'amenities.name', 'amenities.alias', 'amenities.icon_path', 'amenities.is_custom', 'amenities.sort_order']);
                },
                'media' => function ($query) {
                    $query->where('is_avatar', true)
                        ->select(['id', 'mediable_id', 'mediable_type', 'disk', 'path', 'is_avatar']);
                },
            ])
            ->orderBy('name');

        $query->visibleFor($user);

        $venues = $query->get(['id', 'name', 'alias', 'venue_type_id', 'created_at', 'status', 'created_by']);
        $myVenueIds = $this->resolveMyVenueIds($user, $venues);
        $myVenueLookup = array_fill_keys($myVenueIds, true);

        return $venues
            ->map(function (Venue $venue) use ($myVenueLookup, $mediaService, $avatarPlaceholderUrl, $amenityIcons) {
                $settings = $venue->settings;
                $avatar = $venue->media->first();
                $avatarUrl = $avatar ? $mediaService->toPublicUrl($avatar) : $avatarPlaceholderUrl;
                $basePrice = (int) ($settings?->rental_price_rub ?? VenueSettings::DEFAULT_RENTAL_PRICE_RUB);
                $unitMinutes = (int) ($settings?->rental_duration_minutes ?? VenueSettings::DEFAULT_RENTAL_DURATION_MINUTES);
                $feePercent = (int) ($settings?->supervisor_fee_percent ?? VenueSettings::DEFAULT_SUPERVISOR_FEE_PERCENT);
                $feeAmount = (int) ($settings?->supervisor_fee_amount_rub ?? VenueSettings::DEFAULT_SUPERVISOR_FEE_AMOUNT_RUB);
                $isFixed = (bool) ($settings?->supervisor_fee_is_fixed ?? VenueSettings::DEFAULT_SUPERVISOR_FEE_IS_FIXED);

                $commission = 0;
                if ($basePrice > 0) {
                    $commission = $isFixed ? max(0, $feeAmount) : (int) round($basePrice * max(0, $feePercent) / 100);
                }

                $sortedAmenities = $venue->amenities
                    ? $venue->amenities->sortBy(fn ($amenity) => sprintf(
                        '%d-%05d-%s',
                        $amenity->is_custom ? 0 : 1,
                        (int) ($amenity->sort_order ?? 0),
                        $amenity->name
                    ))
                    : collect();

                $amenityIds = $sortedAmenities
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();

                $amenities = $sortedAmenities
                    ->take(10)
                    ->map(fn ($amenity) => [
                        'id' => $amenity->id,
                        'name' => $amenity->name,
                        'alias' => $amenity->alias,
                        'icon_url' => $amenityIcons->getUrl($amenity->icon_path),
                    ])
                    ->values()
                    ->all();

                return [
                    'id' => $venue->id,
                    'name' => $venue->name,
                    'alias' => $venue->alias,
                    'avatar_url' => $avatarUrl,
                    'status' => $venue->status?->value,
                    'address' => $venue->latestAddress?->display_address,
                    'metro' => $venue->latestAddress?->metro?->only(['id', 'name', 'line_name', 'line_color', 'city']),
                    'created_at' => DateFormatter::dateTime($venue->created_at),
                    'type' => $venue->venueType?->only(['id', 'name', 'alias']),
                    'type_slug' => $venue->venueType?->alias ? Str::plural($venue->venueType->alias) : null,
                    'is_my_venue' => (bool) ($myVenueLookup[$venue->id] ?? false),
                    'rental_price_rub' => $basePrice,
                    'rental_duration_minutes' => $unitMinutes,
                    'supervisor_fee_is_fixed' => $isFixed,
                    'supervisor_fee_percent' => $feePercent,
                    'supervisor_fee_amount_rub' => $feeAmount,
                    'price_with_fee_rub' => $basePrice > 0 ? ($basePrice + $commission) : 0,
                    'amenities' => $amenities,
                    'amenity_ids' => $amenityIds,
                ];
            })
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
