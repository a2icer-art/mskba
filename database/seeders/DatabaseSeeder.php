<?php

namespace Database\Seeders;

use App\Domain\Venues\Enums\VenueStatus;
use App\Domain\Venues\Models\Venue;
use App\Domain\Venues\Models\VenueType;
use App\Domain\Addresses\Models\Address;
use App\Domain\Participants\Enums\ParticipantRoleStatus;
use App\Domain\Participants\Models\ParticipantRole;
use App\Domain\Users\Enums\ContactType;
use App\Domain\Users\Enums\UserConfirmedBy;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Models\UserContact;
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
            'login' => 'admin',
        ]));

        $moderator = User::factory()->withProfile()->create(array_merge($commonUserState, [
            'login' => 'moderator',
            'created_by' => $admin->id,
        ]));

        $editor = User::factory()->withProfile()->create(array_merge($commonUserState, [
            'login' => 'editor',
            'created_by' => $admin->id,
        ]));

        $supereditor = User::factory()->withProfile()->create(array_merge($commonUserState, [
            'login' => 'supereditor',
            'created_by' => $admin->id,
        ]));

        $this->seedUserContact($admin, 'admin@example.com', $admin->id);
        $this->seedUserContact($moderator, 'moderator@example.com', $admin->id);
        $this->seedUserContact($editor, 'editor@example.com', $admin->id);
        $this->seedUserContact($supereditor, 'supereditor@example.com', $admin->id);

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

        $participantRoles = [
            ['name' => 'Игрок', 'plural_name' => 'Игроки', 'alias' => 'player'],
            ['name' => 'Тренер', 'plural_name' => 'Тренеры', 'alias' => 'coach'],
            ['name' => 'Судья', 'plural_name' => 'Судьи', 'alias' => 'referee'],
            ['name' => 'Администратор площадки', 'plural_name' => 'Администраторы площадок', 'alias' => 'venue-admin'],
            ['name' => 'Медиа', 'plural_name' => 'Медиа', 'alias' => 'media'],
            ['name' => 'Продавец', 'plural_name' => 'Продавцы', 'alias' => 'seller'],
            ['name' => 'Персонал', 'plural_name' => 'Персонал', 'alias' => 'staff'],
            ['name' => 'Другое', 'plural_name' => 'Другое', 'alias' => 'other'],
        ];

        foreach ($participantRoles as $index => $role) {
            ParticipantRole::query()->create([
                'name' => $role['name'],
                'plural_name' => $role['plural_name'],
                'alias' => $role['alias'],
                'status' => ParticipantRoleStatus::Confirmed,
                'sort' => $index + 1,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
                'confirmed_at' => now(),
                'confirmed_by' => $admin->id,
            ]);
        }

        $venueTypes = collect([
            ['name' => 'Зал', 'plural_name' => 'Залы', 'alias' => 'hall'],
            ['name' => 'Корт', 'plural_name' => 'Корты', 'alias' => 'court'],
            ['name' => 'Уличная площадка', 'plural_name' => 'Уличные площадки', 'alias' => 'outdoor'],
        ])->mapWithKeys(function (array $data) use ($admin): array {
            $venueType = VenueTypeFactory::new()
                ->named($data['name'], $data['alias'], $data['plural_name'], $admin->id)
                ->create();

            return [$data['alias'] => $venueType];
        });

        $this->call(CitySeeder::class);
        $this->call(MetroSeeder::class);

    }

    private function seedUserContact(User $user, string $email, int $updatedBy): void
    {
        $userContact = $user->contacts()
            ->where('type', ContactType::Email)
            ->first();

        if ($userContact) {
            $userContact->update([
                'value' => $email,
                'confirmed_at' => now(),
                'updated_by' => $updatedBy,
            ]);

            return;
        }

        UserContact::query()->create([
            'user_id' => $user->id,
            'type' => ContactType::Email,
            'value' => $email,
            'confirmed_at' => now(),
            'created_by' => $updatedBy,
            'updated_by' => $updatedBy,
        ]);
    }
}
