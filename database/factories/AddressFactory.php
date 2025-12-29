<?php

namespace Database\Factories;

use App\Domain\Addresses\Models\Address;
use App\Domain\Venues\Models\Venue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Addresses\Models\Address>
 */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'city' => 'Москва',
            'metro_id' => fake()->optional()->numberBetween(1, 200),
            'street' => fake()->streetName(),
            'building' => (string) fake()->buildingNumber(),
            'str_address' => fake()->optional()->address(),
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }
}
