<?php

namespace App\Domain\Users\Services;

use App\Domain\Users\Enums\UserStatus;
use App\Domain\Moderation\Enums\ModerationEntityType;
use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Models\ModerationRequest;
use App\Domain\Users\Models\UserProfile;
use App\Domain\Moderation\Requirements\UserModerationRequirements;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class AccountProfileUpdateService
{
    public function updateProfile(User $user, array $data): UserProfile
    {
        $this->ensureProfileAllowed($user, $data);

        $profile = $user->profile;
        if (!$profile) {
            $profile = UserProfile::query()->create([
                'user_id' => $user->id,
                'created_by' => $user->id,
            ]);
        }

        $allowed = $this->getAllowedProfileFields($user);
        $profile->fill(Arr::only($data, $allowed));
        $profile->updated_by = $user->id;
        $profile->save();

        return $profile;
    }

    public function updatePassword(User $user, string $password): void
    {
        $user->update([
            'password' => $password,
            'updated_by' => $user->id,
        ]);
    }

    public function getProfileValidationRules(User $user): array
    {
        if ($this->isRestrictedStatus($user)) {
            $rules = [];
            foreach (UserModerationRequirements::requiredProfileFields() as $field) {
                $rules[$field] = ['prohibited'];
            }
            foreach (UserModerationRequirements::optionalProfileFields() as $field) {
                $rules[$field] = ['nullable', 'string', 'max:255'];
            }
            return $rules;
        }

        return [
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date'],
        ];
    }

    public function getEditableFields(User $user): array
    {
        return $this->getAllowedProfileFields($user);
    }

    private function ensureProfileAllowed(User $user, array $data): void
    {
        if (!$this->isRestrictedStatus($user)) {
            return;
        }

        $restricted = array_diff(UserModerationRequirements::requiredProfileFields(), $this->getAllowedProfileFields($user));
        $attempted = array_intersect($restricted, array_keys($data));

        if ($attempted === []) {
            return;
        }

        $messages = [];
        foreach ($attempted as $field) {
            $messages[$field] = 'Поле недоступно для редактирования после подтверждения.';
        }

        throw ValidationException::withMessages($messages);
    }

    private function getAllowedProfileFields(User $user): array
    {
        return UserModerationRequirements::editableProfileFields($this->isRestrictedStatus($user));
    }

    private function isRestrictedStatus(User $user): bool
    {
        if ($user->status === UserStatus::Confirmed) {
            return true;
        }

        return ModerationRequest::query()
            ->where('entity_type', ModerationEntityType::User->value)
            ->where('entity_id', $user->id)
            ->where('status', ModerationStatus::Pending->value)
            ->exists();
    }
}
