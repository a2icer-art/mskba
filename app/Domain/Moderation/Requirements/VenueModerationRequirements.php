<?php

namespace App\Domain\Moderation\Requirements;

class VenueModerationRequirements
{
    public const REQUIRED_FIELDS = [
        'name',
        'venue_type_id',
        'address',
    ];

    public const OPTIONAL_FIELDS = [
        'commentary',
    ];

    public static function editableFields(bool $confirmed): array
    {
        if ($confirmed) {
            return self::OPTIONAL_FIELDS;
        }

        return array_values(array_unique(array_merge(self::REQUIRED_FIELDS, self::OPTIONAL_FIELDS)));
    }
}
