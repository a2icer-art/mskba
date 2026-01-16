<?php

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Services\AdminLogsService;
use App\Domain\Permissions\Enums\PermissionCode;
use App\Domain\Permissions\Services\PermissionChecker;
use App\Models\User;

class AdminNavigationService
{
    public function __construct(
        private readonly AdminLogsService $logsService,
        private readonly PermissionChecker $permissionChecker
    ) {
    }

    public function getMenuGroups(User $user): array
    {
        $groups = [];
        $moderationItems = [];
        $systemItems = [];
        $contentItems = [];

        if ($this->permissionChecker->can($user, PermissionCode::ModerationAccess)) {
            $moderationItems[] = [
                'label' => 'Пользователи',
                'href' => '/admin/users-moderation',
            ];
            $moderationItems[] = [
                'label' => 'Площадки',
                'href' => '/admin/venues-moderation',
            ];
        }

        if ($this->permissionChecker->can($user, PermissionCode::LogsView)) {
            $systemItems[] = [
                'label' => 'Логи',
                'href' => '/admin/logs',
            ];
        }

        if ($this->permissionChecker->can($user, PermissionCode::ModerationAccess)) {
            $systemItems[] = [
                'label' => 'Настройки',
                'href' => '/admin/settings',
            ];
        }

        if ($moderationItems !== []) {
            $groups[] = [
                'title' => 'Модерация',
                'items' => $moderationItems,
            ];
        }

        if ($systemItems !== []) {
            $groups[] = [
                'title' => 'Система',
                'items' => $systemItems,
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

    public function getMenuItems(User $user): array
    {
        $items = [];

        foreach ($this->getMenuGroups($user) as $group) {
            foreach ($group['items'] as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    public function getDefaultHref(User $user): ?string
    {
        $items = $this->getMenuItems($user);

        return $items[0]['href'] ?? null;
    }
}
