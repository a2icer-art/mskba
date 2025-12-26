<?php

namespace Database\Seeders;

use App\Domain\Venues\Enums\VenueStatus;
use App\Domain\Venues\Models\Venue;
use App\Domain\Venues\Models\VenueType;
use App\Domain\Participants\Enums\ParticipantRoleStatus;
use App\Domain\Participants\Models\ParticipantRole;
use App\Domain\Users\Enums\UserConfirmedBy;
use App\Domain\Users\Enums\UserStatus;
use App\Models\User;
use Database\Factories\VenueTypeFactory;
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
        $commonUserState = [
            'password' => Hash::make('123'),
            'status' => UserStatus::Confirmed,
            'confirmed_at' => now(),
            'confirmed_by' => UserConfirmedBy::Admin,
        ];

        $admin = User::factory()->withProfile()->create(array_merge($commonUserState, [
            'email' => 'admin@example.com',
            'login' => 'admin',
        ]));

        $moderator = User::factory()->withProfile()->create(array_merge($commonUserState, [
            'email' => 'moderator@example.com',
            'login' => 'moderator',
            'created_by' => $admin->id,
        ]));

        $editor = User::factory()->withProfile()->create(array_merge($commonUserState, [
            'email' => 'editor@example.com',
            'login' => 'editor',
            'created_by' => $admin->id,
        ]));

        $supereditor = User::factory()->withProfile()->create(array_merge($commonUserState, [
            'email' => 'supereditor@example.com',
            'login' => 'supereditor',
            'created_by' => $admin->id,
        ]));

        $roles = [
            'admin' => RoleFactory::new()->admin()->create([
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]),
            'moderator' => RoleFactory::new()->moderator()->create([
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]),
            'editor' => RoleFactory::new()->editor()->create([
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]),
            'user' => RoleFactory::new()->user()->create([
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]),
        ];

        $admin->roles()->sync([
            $roles['admin']->id => ['created_by' => $admin->id, 'updated_by' => $admin->id],
            $roles['moderator']->id => ['created_by' => $admin->id, 'updated_by' => $admin->id],
        ]);
        $moderator->roles()->sync([
            $roles['moderator']->id => ['created_by' => $admin->id, 'updated_by' => $admin->id],
            $roles['editor']->id => ['created_by' => $admin->id, 'updated_by' => $admin->id],
        ]);
        $editor->roles()->sync([
            $roles['editor']->id => ['created_by' => $admin->id, 'updated_by' => $admin->id],
        ]);
        $supereditor->roles()->sync([
            $roles['moderator']->id => ['created_by' => $admin->id, 'updated_by' => $admin->id],
            $roles['editor']->id => ['created_by' => $admin->id, 'updated_by' => $admin->id],
        ]);

        $participantRoleNames = [
            'Player',
            'Coach',
            'Referee',
            'Venue admin',
            'Media',
            'Seller',
            'Staff',
            'Other',
        ];

        foreach ($participantRoleNames as $index => $name) {
            ParticipantRole::query()->create([
                'name' => $name,
                'alias' => Str::slug($name),
                'status' => ParticipantRoleStatus::Confirmed,
                'sort' => $index + 1,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
                'confirmed_at' => now(),
                'confirmed_by' => $admin->id,
            ]);
        }

        $venueTypes = collect([
            ['name' => 'Hall', 'alias' => 'hall'],
            ['name' => 'Court', 'alias' => 'court'],
            ['name' => 'Outdoor court', 'alias' => 'outdoor'],
        ])->mapWithKeys(function (array $data) use ($admin): array {
            $venueType = VenueTypeFactory::new()
                ->named($data['name'], $data['alias'], $admin->id)
                ->create();

            return [$data['alias'] => $venueType];
        });

        $venueName = 'Main Hall';
        Venue::query()->create([
            'name' => $venueName,
            'alias' => Str::slug($venueName),
            'status' => VenueStatus::Confirmed,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'confirmed_at' => now(),
            'confirmed_by' => $admin->id,
            'venue_type_id' => $venueTypes['hall']->id,
            'address' => 'Main street, 1',
            'address_id' => null,
        ]);
    }
}
