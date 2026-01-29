<?php

namespace App\Domain\Seo\Services;

use App\Domain\Events\Models\Event;
use App\Domain\Seo\Models\PageMeta;
use App\Domain\Venues\Models\Venue;
use Illuminate\Support\Str;

class PageMetaCatalogService
{
    public function getGroups(): array
    {
        $groups = [];

        $staticItems = $this->buildStaticItems();
        if ($staticItems !== []) {
            $groups[] = [
                'key' => 'static',
                'title' => 'Страницы',
                'items' => $this->attachMeta($staticItems),
            ];
        }

        $venueItems = $this->buildVenueItems();
        if ($venueItems !== []) {
            $groups[] = [
                'key' => 'venues',
                'title' => 'Площадки',
                'items' => $this->attachMeta($venueItems),
            ];
        }

        $eventItems = $this->buildEventItems();
        if ($eventItems !== []) {
            $groups[] = [
                'key' => 'events',
                'title' => 'События',
                'items' => $this->attachMeta($eventItems),
            ];
        }

        return $groups;
    }

    public function getAllowedPairs(): array
    {
        $pairs = [];

        foreach ([$this->buildStaticItems(), $this->buildVenueItems(), $this->buildEventItems()] as $items) {
            foreach ($items as $item) {
                $pairs[] = $this->pairKey($item['page_type'], $item['page_id']);
            }
        }

        return $pairs;
    }

    private function buildStaticItems(): array
    {
        return [
            [
                'page_type' => 'page.home',
                'page_id' => 0,
                'label' => 'Главная',
                'href' => '/',
            ],
            [
                'page_type' => 'page.venues.index',
                'page_id' => 0,
                'label' => 'Площадки',
                'href' => '/venues',
            ],
            [
                'page_type' => 'page.events.index',
                'page_id' => 0,
                'label' => 'События',
                'href' => '/events',
            ],
        ];
    }

    private function buildVenueItems(): array
    {
        $venues = Venue::query()
            ->with(['venueType:id,alias'])
            ->orderBy('name')
            ->get(['id', 'name', 'alias', 'venue_type_id']);

        return $venues->map(function (Venue $venue) {
            $typeAlias = $venue->venueType?->alias;
            $typeSlug = $typeAlias ? Str::plural($typeAlias) : null;
            return [
                'page_type' => 'venue.show',
                'page_id' => $venue->id,
                'label' => $venue->name,
                'href' => $typeSlug ? "/venues/{$typeSlug}/{$venue->alias}" : null,
            ];
        })->all();
    }

    private function buildEventItems(): array
    {
        $events = Event::query()
            ->orderByDesc('starts_at')
            ->get(['id', 'title']);

        return $events->map(function (Event $event) {
            return [
                'page_type' => 'event.show',
                'page_id' => $event->id,
                'label' => $event->title ?: "Событие #{$event->id}",
                'href' => "/events/{$event->id}",
            ];
        })->all();
    }

    private function attachMeta(array $items): array
    {
        $metaMap = $this->loadMetaMap($items);

        return array_map(function (array $item) use ($metaMap) {
            $key = $this->pairKey($item['page_type'], $item['page_id']);
            $meta = $metaMap[$key] ?? ['title' => null, 'description' => null, 'keywords' => null];

            return array_merge($item, [
                'meta' => $meta,
            ]);
        }, $items);
    }

    private function loadMetaMap(array $items): array
    {
        $grouped = [];
        foreach ($items as $item) {
            $grouped[$item['page_type']][] = (int) $item['page_id'];
        }

        $map = [];
        foreach ($grouped as $pageType => $ids) {
            $records = PageMeta::query()
                ->where('page_type', $pageType)
                ->whereIn('page_id', array_unique($ids))
                ->get();

            foreach ($records as $record) {
                $map[$this->pairKey($record->page_type, $record->page_id)] = [
                    'title' => $record->title,
                    'description' => $record->description,
                    'keywords' => $record->keywords,
                ];
            }
        }

        return $map;
    }

    private function pairKey(string $pageType, int $pageId): string
    {
        return "{$pageType}:{$pageId}";
    }
}
