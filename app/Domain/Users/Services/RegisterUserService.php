<?php

namespace App\Domain\Users\Services;

use App\Domain\Participants\Enums\ParticipantRoleAssignmentStatus;
use App\Domain\Participants\Models\ParticipantRole;
use App\Domain\Participants\Models\ParticipantRoleAssignment;
use App\Domain\Participants\Services\ParticipantRoleProfileFactory;
use App\Domain\Users\Enums\RoleStatus;
use App\Domain\Users\Enums\UserRegisteredVia;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Models\Role;
use App\Domain\Users\Models\UserProfile;
use App\Domain\Users\Models\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterUserService
{
    public function __construct(
        private readonly ParticipantRoleProfileFactory $profileFactory
    ) {
    }

    public function register(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $registeredVia = $data['registered_via'] ?? UserRegisteredVia::Site;
            $registrationDetails = $data['registration_details'] ?? null;

            $user = User::query()->create([
                'login' => $data['login'],
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => UserStatus::Unconfirmed,
                'registered_via' => $registeredVia,
                'registration_details' => $registrationDetails,
            ]);

            UserProfile::query()->create([
                'user_id' => $user->id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $role = Role::query()->firstOrCreate(
                ['alias' => 'user'],
                ['name' => 'user', 'status' => RoleStatus::Active]
            );

            UserRole::query()->firstOrCreate(
                ['user_id' => $user->id, 'role_id' => $role->id],
                ['created_by' => $user->id, 'updated_by' => $user->id]
            );

            if (!empty($data['participant_role_id'])) {
                $assignment = ParticipantRoleAssignment::query()->create([
                    'user_id' => $user->id,
                    'participant_role_id' => $data['participant_role_id'],
                    'context_type' => null,
                    'context_id' => null,
                    'status' => ParticipantRoleAssignmentStatus::Confirmed,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                    'confirmed_at' => now(),
                    'confirmed_by' => $user->id,
                    'deleted_by' => null,
                ]);

                $role = ParticipantRole::query()
                    ->whereKey($data['participant_role_id'])
                    ->first();

                if ($role) {
                    $this->profileFactory->createForAlias($role->alias, $assignment->id, $user->id);
                }
            }

            return $user;
        });
    }

}
