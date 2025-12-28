<?php

namespace App\Domain\Filament\Services;

class FilamentNavigationService
{
    public function getMenuItems(int $roleLevel): array
    {
        $items = [];

        if ($roleLevel > 20) {
            $items[] = [
                'label' => 'Модерация пользователей',
                'href' => '/filament/users-moderation',
            ];
        }

        return $items;
    }

    public function getDefaultHref(int $roleLevel): ?string
    {
        $items = $this->getMenuItems($roleLevel);

        return $items[0]['href'] ?? null;
    }
}
