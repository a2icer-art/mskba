<?php

namespace Database\Factories;

use App\Domain\Users\Enums\RoleStatus;
use App\Domain\Users\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Users\Models\Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'name' => $name,
            'alias' => Str::slug($name),
            'status' => fake()->randomElement(RoleStatus::cases()),
            'commentary' => fake()->optional()->sentence(),
        ];
    }
}
