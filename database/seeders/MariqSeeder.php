<?php

namespace Database\Seeders;

use App\Domain\Participants\Enums\ParticipantRoleAssignmentStatus;
use App\Domain\Participants\Enums\ParticipantRoleStatus;
use App\Domain\Participants\Models\ParticipantRole;
use App\Domain\Participants\Models\ParticipantRoleAssignment;
use App\Domain\Participants\Models\PlayerProfile;
use App\Domain\Participants\Services\ParticipantRoleProfileFactory;
use App\Domain\Users\Enums\ContactType;
use App\Domain\Users\Enums\RoleStatus;
use App\Domain\Users\Enums\UserRegisteredVia;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Models\Role;
use App\Domain\Users\Models\UserContact;
use App\Domain\Users\Models\UserProfile;
use App\Domain\Users\Models\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MariqSeeder extends Seeder
{
    public function __construct(
        private readonly ParticipantRoleProfileFactory $profileFactory
    ) {
    }

    public function run(): void
    {
        $admin = User::query()->where('login', 'admin')->first();
        $adminId = $admin?->id;

        $user = User::query()->where('login', 'mariq')->first();
        if (!$user) {
            $user = User::query()->create([
                'login' => 'mariq',
                'password' => Hash::make('123'),
                'status' => UserStatus::Unconfirmed,
                'registered_via' => UserRegisteredVia::Site,
                'registration_details' => null,
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);
        } else {
            $user->update([
                'status' => UserStatus::Unconfirmed,
                'confirmed_at' => null,
                'confirmed_by' => null,
                'registered_via' => UserRegisteredVia::Site,
                'registration_details' => null,
                'updated_by' => $adminId,
            ]);
        }

        UserContact::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'type' => ContactType::Email,
            ],
            [
                'value' => 'mariq@mskba.ru',
                'confirmed_at' => now(),
                'created_by' => $adminId ?? $user->id,
                'updated_by' => $adminId ?? $user->id,
            ]
        );

        $profile = $user->profile;
        if (!$profile) {
            $profile = UserProfile::query()->create([
                'user_id' => $user->id,
                'created_by' => $adminId ?? $user->id,
                'updated_by' => $adminId ?? $user->id,
            ]);
        }

        $profile->update([
            'first_name' => 'Maria',
            'last_name' => 'Kovaleva',
            'gender' => 'female',
            'birth_date' => '1998-06-15',
            'updated_by' => $adminId ?? $user->id,
        ]);

        $role = Role::query()->firstOrCreate(
            ['alias' => 'user'],
            ['name' => 'user', 'status' => RoleStatus::Active]
        );

        UserRole::query()->firstOrCreate(
            ['user_id' => $user->id, 'role_id' => $role->id],
            ['created_by' => $adminId ?? $user->id, 'updated_by' => $adminId ?? $user->id]
        );

        $participantRole = ParticipantRole::query()->firstOrCreate(
            ['alias' => 'player'],
            [
                'name' => 'Игрок',
                'plural_name' => 'Игроки',
                'status' => ParticipantRoleStatus::Confirmed,
                'sort' => 1,
                'created_by' => $adminId ?? $user->id,
                'updated_by' => $adminId ?? $user->id,
                'confirmed_at' => now(),
                'confirmed_by' => $adminId ?? $user->id,
            ]
        );

        $assignment = ParticipantRoleAssignment::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'participant_role_id' => $participantRole->id,
                'context_type' => null,
                'context_id' => null,
            ],
            [
                'status' => ParticipantRoleAssignmentStatus::Confirmed,
                'created_by' => $adminId ?? $user->id,
                'updated_by' => $adminId ?? $user->id,
                'confirmed_at' => now(),
                'confirmed_by' => $adminId ?? $user->id,
                'deleted_by' => null,
            ]
        );

        if (!PlayerProfile::query()
            ->where('participant_role_assignment_id', $assignment->id)
            ->exists()
        ) {
            $this->profileFactory->createForAlias('player', $assignment->id, $adminId ?? $user->id);
        }
    }
}
