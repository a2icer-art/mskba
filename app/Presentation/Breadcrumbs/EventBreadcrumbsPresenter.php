<?php

namespace App\Presentation\Breadcrumbs;

use App\Domain\Events\Models\Event;
use App\Presentation\BasePresenter;

class EventBreadcrumbsPresenter extends BasePresenter
{
    protected function buildData(array $ctx): array
    {
        /** @var Event|null $event */
        $event = $ctx['event'] ?? null;
        $label = $ctx['label'] ?? null;

        $items = [];
        $isRoot = !$event && !$label;

        $items[] = [
            'label' => 'События',
            'href' => $isRoot ? null : '/events',
        ];

        if ($event) {
            $items[] = [
                'label' => $event->title ?: 'Событие',
                'href' => $label ? "/events/{$event->id}" : null,
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
}
