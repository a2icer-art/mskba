<?php

namespace App\Domain\Users\Services;

use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Models\UserProfile;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class AccountProfileUpdateService
{
    private const REQUIRED_FIELDS = ['first_name', 'last_name', 'gender', 'birth_date'];

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
        if ($user->status === UserStatus::Confirmed) {
            return [
                'middle_name' => ['nullable', 'string', 'max:255'],
                'first_name' => ['prohibited'],
                'last_name' => ['prohibited'],
                'gender' => ['prohibited'],
                'birth_date' => ['prohibited'],
            ];
        }

        return [
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date'],
        ];
    }

    private function ensureProfileAllowed(User $user, array $data): void
    {
        if ($user->status !== UserStatus::Confirmed) {
            return;
        }

        $restricted = array_diff(self::REQUIRED_FIELDS, $this->getAllowedProfileFields($user));
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
        if ($user->status === UserStatus::Confirmed) {
            return ['middle_name'];
        }

        return ['first_name', 'last_name', 'middle_name', 'gender', 'birth_date'];
    }
}
