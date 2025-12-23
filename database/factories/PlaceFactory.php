<?php

namespace Database\Factories;

use App\Domain\Places\Enums\PlaceStatus;
use App\Domain\Places\Models\Place;
use App\Domain\Places\Models\PlaceType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Places\Models\Place>
 */
class PlaceFactory extends Factory
{
    protected $model = Place::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'alias' => Str::slug($name),
            'status' => PlaceStatus::Confirmed,
            'created_by' => User::factory(),
            'updated_by' => null,
            'confirmed_at' => now(),
            'confirmed_by' => User::factory(),
            'place_type_id' => PlaceType::factory(),
            'address' => fake()->optional()->address(),
            'address_id' => null,
            'deleted_by' => null,
        ];
    }
}
