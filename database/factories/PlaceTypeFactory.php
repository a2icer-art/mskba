<?php

namespace Database\Factories;

use App\Domain\Places\Models\PlaceType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Places\Models\PlaceType>
 */
class PlaceTypeFactory extends Factory
{
    protected $model = PlaceType::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => $name,
            'alias' => Str::slug($name),
            'created_by' => User::factory(),
            'updated_by' => null,
            'deleted_by' => null,
        ];
    }

    public function named(string $name, ?string $alias = null, ?int $createdBy = null): static
    {
        $alias ??= Str::slug($name);

        return $this->state([
            'name' => $name,
            'alias' => $alias,
            'created_by' => $createdBy ?? User::factory(),
        ]);
    }
}
