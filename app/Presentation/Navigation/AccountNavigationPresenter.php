<?php

namespace App\Presentation\Navigation;

class AccountNavigationPresenter extends NavigationPresenter
{
    protected function buildItems(array $ctx): array
    {
        $items = [
            ['key' => 'user', 'label' => 'Пользователь', 'href' => '/account'],
            ['key' => 'profile', 'label' => 'Профиль', 'href' => '/account/profile'],
            ['key' => 'contacts', 'label' => 'Контакты', 'href' => '/account/contacts'],
            ['key' => 'access', 'label' => 'Доступы', 'href' => '/account/access'],
            ['key' => 'balance', 'label' => 'Баланс', 'href' => '/account/balance'],
        ];

        $roles = $ctx['participantRoles'] ?? [];
        foreach ($roles as $role) {
            $items[] = [
                'key' => 'role-' . $role['id'],
                'label' => $role['label'],
                'href' => '/account/roles/' . $role['id'],
            ];
        }

        return $items;
    }
}
