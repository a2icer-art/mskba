<?php

namespace Database\Factories;

use App\Domain\Users\Models\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Users\Models\UserRole>
 */
class UserRoleFactory extends Factory
{
    protected $model = UserRole::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'role_id' => null,
        ];
    }
}
