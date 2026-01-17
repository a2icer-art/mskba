<?php

namespace App\Presentation\Breadcrumbs;

use App\Presentation\BasePresenter;
use App\Presentation\Navigation\AccountNavigationPresenter;

class AccountBreadcrumbsPresenter extends BasePresenter
{
    protected function buildData(array $ctx): array
    {
        $activeTab = $ctx['activeTab'] ?? 'user';
        $participantRoles = $ctx['participantRoles'] ?? [];

        $navigation = app(AccountNavigationPresenter::class)->present([
            'participantRoles' => $participantRoles,
        ])['data'];

        $flatItems = collect($navigation)->flatMap(function (array $item): array {
            if (isset($item['items']) && is_array($item['items'])) {
                return $item['items'];
            }
            return [$item];
        });

        $currentItem = $flatItems
            ->first(fn (array $item) => ($item['key'] ?? null) === $activeTab);

        $items = [
            [
                'label' => 'Аккаунт',
                'href' => $activeTab === 'user' ? null : '/account',
            ],
        ];

        if ($activeTab !== 'user' && $currentItem) {
            $items[] = [
                'label' => $currentItem['label'] ?? 'Раздел',
                'href' => null,
            ];
        }

        return $items;
    }
}
