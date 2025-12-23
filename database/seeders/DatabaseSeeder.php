<?php

namespace Database\Seeders;

use App\Domain\Places\Enums\PlaceStatus;
use App\Domain\Places\Models\Place;
use App\Domain\Places\Models\PlaceType;
use App\Domain\Users\Enums\UserConfirmedBy;
use App\Domain\Users\Enums\UserStatus;
use App\Models\User;
use Database\Factories\PlaceTypeFactory;
use Database\Factories\RoleFactory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

        $placeTypes = collect([
            ['name' => 'Hall', 'alias' => 'hall'],
            ['name' => 'Court', 'alias' => 'court'],
            ['name' => 'Outdoor court', 'alias' => 'outdoor'],
        ])->mapWithKeys(function (array $data) use ($admin): array {
            $placeType = PlaceTypeFactory::new()
                ->named($data['name'], $data['alias'], $admin->id)
                ->create();

            return [$data['alias'] => $placeType];
        });

        $placeName = 'Main Hall';
        Place::query()->create([
            'name' => $placeName,
            'alias' => Str::slug($placeName),
            'status' => PlaceStatus::Confirmed,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'confirmed_at' => now(),
            'confirmed_by' => $admin->id,
            'place_type_id' => $placeTypes['hall']->id,
            'address' => 'Main street, 1',
            'address_id' => null,
        ]);
    }
}
