<?php

namespace Database\Factories;

use App\Models\User;
use App\Domain\Users\Enums\UserConfirmedBy;
use App\Domain\Users\Enums\UserRegisteredVia;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Models\UserEmail;
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
            'login' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'status' => $confirmed ? UserStatus::Confirmed : UserStatus::Unconfirmed,
            'confirmed_at' => $confirmed ? now() : null,
            'confirmed_by' => $confirmed ? fake()->randomElement(UserConfirmedBy::cases()) : null,
            'commentary' => fake()->optional()->sentence(),
            'registered_via' => UserRegisteredVia::Site,
            'registration_details' => null,
        ];
    }

    public function withProfile(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->profile()->create(
                UserProfileFactory::new()->make()->toArray() + [
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]
            );
        });
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            $isConfirmed = $user->status === UserStatus::Confirmed;

            UserEmail::query()->create([
                'user_id' => $user->id,
                'email' => fake()->unique()->safeEmail(),
                'confirmed_at' => $isConfirmed ? now() : null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });
    }
}
