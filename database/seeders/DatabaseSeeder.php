<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = \Database\Factories\RoleFactory::new()->count(3)->create();
        $user = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'login' => 'admin',
            'password' => Hash::make('Asdqwe12#'),
        ]);

        User::factory()->create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'login' => 'user',
            'password' => Hash::make('123'),
        ]);

        $user->profile()->create(
            \Database\Factories\UserProfileFactory::new()->make()->toArray()
        );

        $roles->each(function ($role) use ($user): void {
            \Database\Factories\UserRoleFactory::new()->create([
                'user_id' => $user->id,
                'role_id' => $role->id,
            ]);
        });
    }
}
