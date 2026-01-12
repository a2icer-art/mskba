<?php

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Services\AdminLogsService;

class AdminNavigationService
{
    public function __construct(
        private readonly AdminLogsService $logsService
    ) {
    }

    public function getMenuGroups(int $roleLevel): array
    {
        $groups = [];
        $moderationItems = [];
        $contentItems = [];

        if ($roleLevel > 20) {
            $moderationItems[] = [
                'label' => 'Пользователи',
                'href' => '/admin/users-moderation',
            ];
            $moderationItems[] = [
                'label' => 'Площадки',
                'href' => '/admin/venues-moderation',
            ];

            $moderationItems[] = [
                'label' => 'Логи',
                'href' => '/admin/logs',
            ];
        }

        if ($moderationItems !== []) {
            $groups[] = [
                'title' => 'Модерация',
                'items' => $moderationItems,
            ];
        }

        if ($contentItems !== []) {
            $groups[] = [
                'title' => 'Контент',
                'items' => $contentItems,
            ];
        }

        return $groups;
    }

    public function getMenuItems(int $roleLevel): array
    {
        $items = [];

        foreach ($this->getMenuGroups($roleLevel) as $group) {
            foreach ($group['items'] as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    public function getDefaultHref(int $roleLevel): ?string
    {
        $items = $this->getMenuItems($roleLevel);

        return $items[0]['href'] ?? null;
    }
}
