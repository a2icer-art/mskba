<?php

namespace App\Presentation\Breadcrumbs;

use App\Domain\Venues\Models\Venue;
use App\Domain\Venues\Models\VenueType;
use App\Presentation\BasePresenter;
use Illuminate\Support\Str;

class VenueBreadcrumbsPresenter extends BasePresenter
{
    protected function buildData(array $ctx): array
    {
        /** @var Venue|null $venue */
        $venue = $ctx['venue'] ?? null;
        $typeSlug = $ctx['typeSlug'] ?? null;
        $label = $ctx['label'] ?? null;

        $type = $ctx['venueType'] ?? null;
        if (!$type && $venue?->venueType) {
            $type = $venue->venueType;
        }

        if (!$type && $typeSlug) {
            $type = $this->resolveType($typeSlug);
        }

        $items = [];
        $isRoot = !$type && !$venue && !$label;

        $items[] = [
            'label' => 'Площадки',
            'href' => $isRoot ? null : '/venues',
        ];

        if ($type) {
            $typeLabel = $type->plural_name ?: $type->name;
            $typeHref = '/venues/' . ($typeSlug ?: Str::plural($type->alias));

            $items[] = [
                'label' => $typeLabel,
                'href' => ($venue || $label) ? $typeHref : null,
            ];
        }

        if ($venue) {
            $typePath = $typeSlug ?: ($type ? Str::plural($type->alias) : '');
            $venueHref = $typePath !== '' ? "/venues/{$typePath}/{$venue->alias}" : null;

            $items[] = [
                'label' => $venue->name,
                'href' => $label ? $venueHref : null,
            ];
        }

        if ($label) {
            $items[] = [
                'label' => $label,
                'href' => null,
            ];
        }

        return $items;
    }

    private function resolveType(string $typeSlug): ?VenueType
    {
        $venueType = VenueType::query()
            ->where('alias', $typeSlug)
            ->first(['id', 'name', 'plural_name', 'alias']);

        if ($venueType) {
            return $venueType;
        }

        $singular = Str::singular($typeSlug);
        if ($singular === $typeSlug) {
            return null;
        }

        return VenueType::query()
            ->where('alias', $singular)
            ->first(['id', 'name', 'plural_name', 'alias']);
    }
}
