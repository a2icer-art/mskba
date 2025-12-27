<?php

namespace Database\Factories;

use App\Domain\Participants\Enums\ParticipantRoleStatus;
use App\Domain\Participants\Models\ParticipantRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Participants\Models\ParticipantRole>
 */
class ParticipantRoleFactory extends Factory
{
    protected $model = ParticipantRole::class;

    public function definition(): array
    {
        $roles = $this->rolePresets();
        $role = $roles[array_rand($roles)];

        return [
            'name' => $role['name'],
            'plural_name' => $role['plural_name'],
            'alias' => $role['alias'],
            'status' => ParticipantRoleStatus::Confirmed,
            'sort' => $role['sort'],
            'created_by' => User::factory(),
            'updated_by' => null,
            'confirmed_at' => now(),
            'confirmed_by' => User::factory(),
        ];
    }

    public function named(string $name, string $alias, string $pluralName, int $sort, ?int $createdBy = null): static
    {
        return $this->state([
            'name' => $name,
            'plural_name' => $pluralName,
            'alias' => $alias,
            'status' => ParticipantRoleStatus::Confirmed,
            'sort' => $sort,
            'created_by' => $createdBy ?? User::factory(),
            'confirmed_at' => now(),
            'confirmed_by' => $createdBy ?? User::factory(),
        ]);
    }

    private function rolePresets(): array
    {
        return [
            ['name' => 'Игрок', 'plural_name' => 'Игроки', 'alias' => 'player', 'sort' => 1],
            ['name' => 'Тренер', 'plural_name' => 'Тренеры', 'alias' => 'coach', 'sort' => 2],
            ['name' => 'Судья', 'plural_name' => 'Судьи', 'alias' => 'referee', 'sort' => 3],
            ['name' => 'Администратор площадки', 'plural_name' => 'Администраторы площадок', 'alias' => 'venue-admin', 'sort' => 4],
            ['name' => 'Медиа', 'plural_name' => 'Медиа', 'alias' => 'media', 'sort' => 5],
            ['name' => 'Продавец', 'plural_name' => 'Продавцы', 'alias' => 'seller', 'sort' => 6],
            ['name' => 'Персонал', 'plural_name' => 'Персонал', 'alias' => 'staff', 'sort' => 7],
            ['name' => 'Другое', 'plural_name' => 'Другое', 'alias' => 'other', 'sort' => 8],
        ];
    }
}
