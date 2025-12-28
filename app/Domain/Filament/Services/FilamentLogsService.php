<?php

namespace App\Domain\Filament\Services;

class FilamentLogsService
{
    public function getEntities(): array
    {
        return [
            [
                'key' => 'users',
                'label' => 'Пользователи',
                'model' => \App\Models\User::class,
                'href' => '/filament/logs/users',
            ],
            [
                'key' => 'user-profiles',
                'label' => 'Профили пользователей',
                'model' => \App\Domain\Users\Models\UserProfile::class,
                'href' => '/filament/logs/user-profiles',
            ],
            [
                'key' => 'roles',
                'label' => 'Роли',
                'model' => \App\Domain\Users\Models\Role::class,
                'href' => '/filament/logs/roles',
            ],
            [
                'key' => 'user-roles',
                'label' => 'Роли пользователей',
                'model' => \App\Domain\Users\Models\UserRole::class,
                'href' => '/filament/logs/user-roles',
            ],
            [
                'key' => 'venues',
                'label' => 'Площадки',
                'model' => \App\Domain\Venues\Models\Venue::class,
                'href' => '/filament/logs/venues',
            ],
            [
                'key' => 'venue-types',
                'label' => 'Типы площадок',
                'model' => \App\Domain\Venues\Models\VenueType::class,
                'href' => '/filament/logs/venue-types',
            ],
            [
                'key' => 'participant-roles',
                'label' => 'Роли участников',
                'model' => \App\Domain\Participants\Models\ParticipantRole::class,
                'href' => '/filament/logs/participant-roles',
            ],
            [
                'key' => 'participant-role-assignments',
                'label' => 'Назначения ролей',
                'model' => \App\Domain\Participants\Models\ParticipantRoleAssignment::class,
                'href' => '/filament/logs/participant-role-assignments',
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
