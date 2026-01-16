<?php

namespace App\Domain\Admin\Services;

class AdminLogsService
{
    public function getEntities(): array
    {
        return [
            [
                'key' => 'users',
                'label' => 'Пользователи',
                'model' => \App\Models\User::class,
                'href' => '/admin/logs/users',
            ],
            [
                'key' => 'user-profiles',
                'label' => 'Профили пользователей',
                'model' => \App\Domain\Users\Models\UserProfile::class,
                'href' => '/admin/logs/user-profiles',
            ],
            [
                'key' => 'roles',
                'label' => 'Роли',
                'model' => \App\Domain\Users\Models\Role::class,
                'href' => '/admin/logs/roles',
            ],
            [
                'key' => 'user-roles',
                'label' => 'Роли пользователей',
                'model' => \App\Domain\Users\Models\UserRole::class,
                'href' => '/admin/logs/user-roles',
            ],
            [
                'key' => 'venues',
                'label' => 'Площадки',
                'model' => \App\Domain\Venues\Models\Venue::class,
                'href' => '/admin/logs/venues',
            ],
            [
                'key' => 'addresses',
                'label' => 'Адреса',
                'model' => \App\Domain\Addresses\Models\Address::class,
                'href' => '/admin/logs/addresses',
            ],
            [
                'key' => 'venue-types',
                'label' => 'Типы площадок',
                'model' => \App\Domain\Venues\Models\VenueType::class,
                'href' => '/admin/logs/venue-types',
            ],
            [
                'key' => 'metros',
                'label' => 'Станции метро',
                'model' => \App\Domain\Metros\Models\Metro::class,
                'href' => '/admin/logs/metros',
            ],
            [
                'key' => 'participant-roles',
                'label' => 'Роли участников',
                'model' => \App\Domain\Participants\Models\ParticipantRole::class,
                'href' => '/admin/logs/participant-roles',
            ],
            [
                'key' => 'participant-role-assignments',
                'label' => 'Назначения ролей',
                'model' => \App\Domain\Participants\Models\ParticipantRoleAssignment::class,
                'href' => '/admin/logs/participant-role-assignments',
            ],
            [
                'key' => 'events',
                'label' => 'События',
                'model' => \App\Domain\Events\Models\Event::class,
                'href' => '/admin/logs/events',
            ],
            [
                'key' => 'event-bookings',
                'label' => 'Бронирования',
                'model' => \App\Domain\Events\Models\EventBooking::class,
                'href' => '/admin/logs/event-bookings',
            ],
            [
                'key' => 'balances',
                'label' => 'Балансы',
                'model' => \App\Domain\Balances\Models\Balance::class,
                'href' => '/admin/logs/balances',
            ],
            [
                'key' => 'balance-transactions',
                'label' => 'Операции баланса',
                'model' => \App\Domain\Balances\Models\BalanceTransaction::class,
                'href' => '/admin/logs/balance-transactions',
            ],
        ];
    }

    public function getEntity(string $key): ?array
    {
        foreach ($this->getEntities() as $entity) {
            if ($entity['key'] === $key) {
                return $entity;
            }
        }

        return null;
    }

    public function getLabelByModel(string $model): ?string
    {
        foreach ($this->getEntities() as $entity) {
            if (($entity['model'] ?? null) === $model) {
                return $entity['label'];
            }
        }

        return null;
    }
}
