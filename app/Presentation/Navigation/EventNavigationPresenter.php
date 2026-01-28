<?php

namespace App\Presentation\Navigation;

use App\Domain\Events\Models\EventType;

class EventNavigationPresenter extends NavigationPresenter
{
    protected function resolveTitle(array $ctx): string
    {
        return $ctx['title'] ?? 'События';
    }

    protected function buildItems(array $ctx): array
    {
        $items = [
            [
                'label' => 'Все события',
                'href' => '/events',
            ],
        ];

        $types = EventType::query()
            ->orderBy('label')
            ->get(['code', 'label']);

        foreach ($types as $type) {
            if (!$type->code) {
                continue;
            }
            $items[] = [
                'label' => $type->label ?: $type->code,
                'href' => '/events?type=' . $type->code,
            ];
        }

        return $items;
    }
}
