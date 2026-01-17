<?php

namespace App\Presentation\Navigation;

class AccountNavigationPresenter extends NavigationPresenter
{
    protected function buildItems(array $ctx): array
    {
        $messageCounters = $ctx['messageCounters'] ?? [];
        $unreadMessages = (int) ($messageCounters['unread_messages'] ?? 0);

        $mainItems = [
            ['key' => 'user', 'label' => 'Пользователь', 'href' => '/account'],
            ['key' => 'profile', 'label' => 'Профиль', 'href' => '/account/profile'],
            ['key' => 'contacts', 'label' => 'Контакты', 'href' => '/account/contacts'],
            ['key' => 'balance', 'label' => 'Баланс', 'href' => '/account/balance'],
            [
                'key' => 'messages',
                'label' => 'Сообщения',
                'href' => '/account/messages',
                'badge' => $unreadMessages > 0 ? $unreadMessages : null,
            ],
        ];

        $roles = $ctx['participantRoles'] ?? [];
        foreach ($roles as $role) {
            $mainItems[] = [
                'key' => 'role-' . $role['id'],
                'label' => $role['label'],
                'href' => '/account/roles/' . $role['id'],
            ];
        }

        $settingsItems = [
            ['key' => 'access', 'label' => 'Доступы', 'href' => '/account/access'],
            ['key' => 'messages-settings', 'label' => 'Сообщения', 'href' => '/account/settings/messages'],
        ];

        return [
            [
                'title' => '',
                'items' => $mainItems,
            ],
            [
                'title' => 'Настройки',
                'items' => $settingsItems,
            ],
        ];
    }
}
