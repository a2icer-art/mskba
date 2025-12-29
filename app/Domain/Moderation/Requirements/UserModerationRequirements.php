<?php

namespace App\Domain\Moderation\Requirements;

class UserModerationRequirements
{
    public const REQUIRED_PROFILE_FIELDS = [
        'first_name',
        'last_name',
        'gender',
        'birth_date',
    ];

    public const OPTIONAL_PROFILE_FIELDS = [
        'middle_name',
    ];

    public static function editableProfileFields(bool $confirmed): array
    {
        if ($confirmed) {
            return self::OPTIONAL_PROFILE_FIELDS;
        }

        return array_values(array_unique(array_merge(
            self::REQUIRED_PROFILE_FIELDS,
            self::OPTIONAL_PROFILE_FIELDS
        )));
    }

    public static function requiredProfileFields(): array
    {
        return self::REQUIRED_PROFILE_FIELDS;
    }

    public static function optionalProfileFields(): array
    {
        return self::OPTIONAL_PROFILE_FIELDS;
    }
}
