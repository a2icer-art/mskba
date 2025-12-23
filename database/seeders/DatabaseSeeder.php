<?php

namespace Database\Seeders;

use App\Domain\Users\Enums\UserConfirmedBy;
use App\Domain\Users\Enums\UserStatus;
use App\Models\User;
use Database\Factories\RoleFactory;
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
        $roles = [
            'admin' => RoleFactory::new()->admin()->create(),
            'moderator' => RoleFactory::new()->moderator()->create(),
            'editor' => RoleFactory::new()->editor()->create(),
        ];

        $commonUserState = [
            'password' => Hash::make('123'),
            'status' => UserStatus::Confirmed,
            'confirmed_at' => now(),
            'confirmed_by' => UserConfirmedBy::Admin,
        ];

        $admin = User::factory()->withProfile()->create(array_merge($commonUserState, [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'login' => 'admin',
        ]));

        $moderator = User::factory()->withProfile()->create(array_merge($commonUserState, [
            'name' => 'Moderator',
            'email' => 'moderator@example.com',
            'login' => 'moderator',
        ]));

        $editor = User::factory()->withProfile()->create(array_merge($commonUserState, [
            'name' => 'Editor',
            'email' => 'editor@example.com',
            'login' => 'editor',
        ]));

        $supereditor = User::factory()->withProfile()->create(array_merge($commonUserState, [
            'name' => 'Supereditor',
            'email' => 'supereditor@example.com',
            'login' => 'supereditor',
        ]));

        $admin->roles()->sync([$roles['admin']->id, $roles['moderator']->id]);
        $moderator->roles()->sync([$roles['moderator']->id, $roles['editor']->id]);
        $editor->roles()->sync([$roles['editor']->id]);
        $supereditor->roles()->sync([$roles['moderator']->id, $roles['editor']->id]);
    }
}
