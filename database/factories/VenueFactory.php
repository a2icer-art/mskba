<?php

namespace Database\Factories;

use App\Domain\Venues\Enums\VenueStatus;
use App\Domain\Venues\Models\Venue;
use App\Domain\Venues\Models\VenueType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Venues\Models\Venue>
 */
class VenueFactory extends Factory
{
    protected $model = Venue::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'alias' => Str::slug($name),
            'status' => VenueStatus::Confirmed,
            'created_by' => User::factory(),
            'updated_by' => null,
            'confirmed_at' => now(),
            'confirmed_by' => User::factory(),
            'venue_type_id' => VenueType::factory(),
            'address' => fake()->optional()->address(),
            'address_id' => null,
            'deleted_by' => null,
        ];
    }
}
