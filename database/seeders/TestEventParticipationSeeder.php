<?php

namespace Database\Seeders;

use App\Domain\Participants\Enums\ParticipantRoleAssignmentStatus;
use App\Domain\Participants\Enums\ParticipantRoleStatus;
use App\Domain\Participants\Models\CoachProfile;
use App\Domain\Participants\Models\ParticipantRole;
use App\Domain\Participants\Models\ParticipantRoleAssignment;
use App\Domain\Participants\Models\PlayerProfile;
use App\Domain\Participants\Models\RefereeProfile;
use App\Domain\Participants\Services\ParticipantRoleProfileFactory;
use App\Domain\Users\Enums\ContactType;
use App\Domain\Users\Enums\RoleStatus;
use App\Domain\Users\Enums\UserConfirmedBy;
use App\Domain\Users\Enums\UserRegisteredVia;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Models\Role;
use App\Domain\Users\Models\UserContact;
use App\Domain\Users\Models\UserProfile;
use App\Domain\Users\Models\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestEventParticipationSeeder extends Seeder
{
    public function __construct(
        private readonly ParticipantRoleProfileFactory $profileFactory
    ) {
    }

    public function run(): void
    {
        $admin = User::query()->where('login', 'admin')->first();
        $adminId = $admin?->id;

        $participantRoles = $this->ensureParticipantRoles($adminId);
        $userRole = Role::query()->firstOrCreate(
            ['alias' => 'user'],
            ['name' => 'user', 'status' => RoleStatus::Active]
        );

        $this->seedUsersForRole('player', 5, $participantRoles['player'], $userRole, $adminId);
        $this->seedUsersForRole('coach', 2, $participantRoles['coach'], $userRole, $adminId);
        $this->seedUsersForRole('referee', 1, $participantRoles['referee'], $userRole, $adminId);
    }

    private function ensureParticipantRoles(?int $adminId): array
    {
        $roles = [
            'player' => ['name' => 'Игрок', 'plural_name' => 'Игроки', 'sort' => 1],
            'coach' => ['name' => 'Тренер', 'plural_name' => 'Тренеры', 'sort' => 2],
            'referee' => ['name' => 'Судья', 'plural_name' => 'Судьи', 'sort' => 3],
        ];

        $result = [];
        foreach ($roles as $alias => $data) {
            $role = ParticipantRole::query()->updateOrCreate(
                ['alias' => $alias],
                [
                    'name' => $data['name'],
                    'plural_name' => $data['plural_name'],
                    'status' => ParticipantRoleStatus::Confirmed,
                    'sort' => $data['sort'],
                    'created_by' => $adminId,
                    'updated_by' => $adminId,
                    'confirmed_at' => now(),
                    'confirmed_by' => $adminId,
                ]
            );
            $result[$alias] = $role;
        }

        return $result;
    }

    private function seedUsersForRole(
        string $alias,
        int $count,
        ParticipantRole $participantRole,
        Role $userRole,
        ?int $adminId
    ): void {
        $profileMap = [
            'player' => PlayerProfile::class,
            'coach' => CoachProfile::class,
            'referee' => RefereeProfile::class,
        ];

        for ($index = 1; $index <= $count; $index += 1) {
            $login = "test-{$alias}-{$index}";
            $email = "{$login}@mskba.ru";

            $user = User::query()->where('login', $login)->first();
            $confirmedBy = $adminId ?? $user?->id;

            if (!$user) {
                $user = User::query()->create([
                    'login' => $login,
                    'password' => Hash::make('password'),
                    'status' => UserStatus::Confirmed,
                    'confirmed_at' => now(),
                    'confirmed_by' => UserConfirmedBy::Other,
                    'registered_via' => UserRegisteredVia::Site,
                    'registration_details' => null,
                    'created_by' => $adminId,
                    'updated_by' => $adminId,
                ]);

                if (!$adminId) {
                    $user->update([
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                }
            } else {
                $user->update([
                    'status' => UserStatus::Confirmed,
                    'confirmed_at' => now(),
                    'confirmed_by' => UserConfirmedBy::Other,
                    'registered_via' => UserRegisteredVia::Site,
                    'registration_details' => null,
                    'updated_by' => $adminId ?? $user->id,
                ]);
            }

            UserProfile::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'created_by' => $adminId ?? $user->id,
                    'updated_by' => $adminId ?? $user->id,
                ]
            );

            UserContact::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => ContactType::Email,
                ],
                [
                    'value' => $email,
                    'confirmed_at' => now(),
                    'created_by' => $adminId ?? $user->id,
                    'updated_by' => $adminId ?? $user->id,
                ]
            );

            UserRole::query()->firstOrCreate(
                ['user_id' => $user->id, 'role_id' => $userRole->id],
                ['created_by' => $adminId ?? $user->id, 'updated_by' => $adminId ?? $user->id]
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
                    'confirmed_by' => $confirmedBy,
                    'deleted_by' => null,
                ]
            );

            $profileClass = $profileMap[$alias] ?? null;
            if ($profileClass && !$profileClass::query()
                ->where('participant_role_assignment_id', $assignment->id)
                ->exists()
            ) {
                $this->profileFactory->createForAlias($alias, $assignment->id, $adminId ?? $user->id);
            }
        }
    }
}
