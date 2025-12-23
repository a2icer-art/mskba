<?php

namespace Database\Factories;

use App\Domain\Users\Enums\UserConfirmedBy;
use App\Domain\Users\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $confirmed = fake()->boolean(60);

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'login' => fake()->unique()->userName(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'status' => $confirmed ? UserStatus::Confirmed : UserStatus::Unconfirmed,
            'confirmed_at' => $confirmed ? now() : null,
            'confirmed_by' => $confirmed ? fake()->randomElement(UserConfirmedBy::cases()) : null,
            'commentary' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
