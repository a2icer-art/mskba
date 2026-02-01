<?php

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Services\AdminLogsService;
use App\Domain\Permissions\Enums\PermissionCode;
use App\Domain\Permissions\Services\PermissionChecker;
use App\Models\User;
use App\Domain\Moderation\Models\ModerationRequest;
use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Enums\ModerationEntityType;

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
            // Counts of pending moderation requests
            $totalPending = ModerationRequest::where('status', ModerationStatus::Pending->value)->count();
            $userPending = ModerationRequest::where('status', ModerationStatus::Pending->value)
                ->where('entity_type', ModerationEntityType::User->value)
                ->count();
            $venuePending = ModerationRequest::where('status', ModerationStatus::Pending->value)
                ->where('entity_type', ModerationEntityType::Venue->value)
                ->count();
            $contractPending = ModerationRequest::where('status', ModerationStatus::Pending->value)
                ->where('entity_type', ModerationEntityType::VenueContract->value)
                ->count();

            $moderationItems[] = [
                'label' => 'Пользователи',
                'href' => '/admin/users-moderation',
                'badge' => $userPending,
            ];
            $moderationItems[] = [
                'label' => 'Площадки',
                'href' => '/admin/venues-moderation',
                'badge' => $venuePending,
            ];
            $moderationItems[] = [
                'label' => 'Контракты',
                'href' => '/admin/contracts-moderation',
                'badge' => $contractPending,
            ];

            // Attach total pending as meta on moderation group via special key
            // The presenter will include this in the groups data so frontend can show header badge.
            $moderationGroupMeta = ['total_pending' => $totalPending];
        }

        if ($this->permissionChecker->can($user, PermissionCode::AdminAccess)
            && $this->permissionChecker->can($user, PermissionCode::LogsView)) {
            $systemItems[] = [
                'label' => 'Логи',
                'href' => '/admin/logs',
            ];
        }

        if ($this->permissionChecker->can($user, PermissionCode::AdminAccess)) {
            $systemItems[] = [
                'label' => 'Пользователи',
                'href' => '/admin/users',
            ];
            $systemItems[] = [
                'label' => 'Площадки',
                'href' => '/admin/venues',
            ];
            $systemItems[] = [
                'label' => 'События',
                'href' => '/admin/events',
            ];
            $systemItems[] = [
                'label' => 'Настройки',
                'href' => '/admin/settings',
            ];
            $systemItems[] = [
                'label' => 'Балансы',
                'href' => '/admin/balances',
            ];
        }

        if ($this->permissionChecker->can($user, PermissionCode::SeoManage)) {
            $contentItems[] = [
                'label' => 'SEO',
                'href' => '/admin/seo',
            ];
        }

        if ($moderationItems !== []) {
            $group = [
                'title' => 'Модерация',
                'items' => $moderationItems,
            ];
            if (isset($moderationGroupMeta)) {
                $group['meta'] = $moderationGroupMeta;
            }
            $groups[] = $group;
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
