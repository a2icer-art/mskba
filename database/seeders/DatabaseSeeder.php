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
use App\Domain\Users\Models\Role;
use App\Models\User;
use Database\Factories\RoleFactory;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
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

        $admin = $this->getOrCreateUser('admin', null, $commonUserState);
        $moderator = $this->getOrCreateUser('moderator', $admin->id, $commonUserState);
        $editor = $this->getOrCreateUser('editor', $admin->id, $commonUserState);
        $supereditor = $this->getOrCreateUser('supereditor', $admin->id, $commonUserState);

        $this->seedUserContact($admin, 'admin@example.com', $admin->id);
        $this->seedUserContact($moderator, 'moderator@example.com', $admin->id);
        $this->seedUserContact($editor, 'editor@example.com', $admin->id);
        $this->seedUserContact($supereditor, 'supereditor@example.com', $admin->id);

        $roles = [
            'admin' => $this->getOrCreateRole('admin', 40, $admin->id),
            'moderator' => $this->getOrCreateRole('moderator', 30, $admin->id),
            'editor' => $this->getOrCreateRole('editor', 20, $admin->id),
            'user' => $this->getOrCreateRole('user', 10, $admin->id),
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

        $this->call([
            PermissionSeeder::class,
            RolePermissionSeeder::class,
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
            ParticipantRole::query()->updateOrCreate(
                ['alias' => $role['alias']],
                [
                    'name' => $role['name'],
                    'plural_name' => $role['plural_name'],
                    'status' => ParticipantRoleStatus::Confirmed,
                    'sort' => $index + 1,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                    'confirmed_at' => now(),
                    'confirmed_by' => $admin->id,
                ]
            );
        }

        $venueTypes = collect([
            ['name' => 'Зал', 'plural_name' => 'Залы', 'alias' => 'hall'],
            ['name' => 'Корт', 'plural_name' => 'Корты', 'alias' => 'court'],
            ['name' => 'Уличная площадка', 'plural_name' => 'Уличные площадки', 'alias' => 'outdoor'],
        ])->mapWithKeys(function (array $data) use ($admin): array {
            $venueType = VenueType::query()->updateOrCreate(
                ['alias' => $data['alias']],
                [
                    'name' => $data['name'],
                    'plural_name' => $data['plural_name'],
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ]
            );

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

    private function getOrCreateUser(string $login, ?int $createdBy, array $commonUserState): User
    {
        $user = User::query()->where('login', $login)->first();
        if ($user) {
            return $user;
        }

        $data = array_merge($commonUserState, [
            'login' => $login,
        ]);

        if ($createdBy) {
            $data['created_by'] = $createdBy;
        }

        return User::factory()->withProfile()->create($data);
    }

    private function getOrCreateRole(string $alias, int $level, int $createdBy): Role
    {
        $role = Role::query()->where('alias', $alias)->first();
        if ($role) {
            return $role;
        }

        return RoleFactory::new()->named($alias)->create([
            'level' => $level,
            'created_by' => $createdBy,
            'updated_by' => $createdBy,
        ]);
    }
}
