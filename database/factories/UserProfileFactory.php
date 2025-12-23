<?php

namespace Database\Factories;

use App\Domain\Users\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Users\Models\UserProfile>
 */
class UserProfileFactory extends Factory
{
    protected $model = UserProfile::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'middle_name' => fake()->optional()->firstName(),
            'gender' => fake()->optional()->randomElement(['male', 'female']),
            'birth_date' => fake()->optional()->date(),
        ];
    }
}
