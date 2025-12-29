<?php

namespace App\Domain\Users\Resources;

use App\Domain\Users\Models\UserProfile;
use App\Support\DateFormatter;

class UserProfileViewResource
{
    public static function make(?UserProfile $profile): ?array
    {
        if (!$profile) {
            return null;
        }

        $genderLabel = match ($profile->gender) {
            'male' => 'Мужской',
            'female' => 'Женский',
            default => $profile->gender,
        };

        return [
            'first_name' => $profile->first_name,
            'last_name' => $profile->last_name,
            'middle_name' => $profile->middle_name,
            'gender' => $profile->gender,
            'gender_label' => $genderLabel ?: '-',
            'birth_date' => DateFormatter::date($profile->birth_date),
            'birth_date_display' => $profile->birth_date?->format('d-m-Y') ?? '-',
        ];
    }
}
