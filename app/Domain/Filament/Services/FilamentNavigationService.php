<?php

namespace App\Domain\Filament\Services;

class FilamentNavigationService
{
    public function getMenuGroups(int $roleLevel): array
    {
        $groups = [];
        $moderationItems = [];
        $contentItems = [];

        if ($roleLevel > 20) {
            $moderationItems[] = [
                'label' => 'Пользователи',
                'href' => '/filament/users-moderation',
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
