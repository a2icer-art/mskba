<?php

namespace App\Domain\Moderation\Requirements;

class VenueModerationRequirements
{
    public const REQUIRED_VENUE_FIELDS = [
        'name',
        'venue_type_id',
    ];

    public const REQUIRED_ADDRESS_FIELDS = [
        'city',
        'street',
        'building',
    ];

    public const OPTIONAL_FIELDS = [
        'commentary',
       // 'metro_id',
        'str_address',
    ];

    public static function editableFields(bool $confirmed): array
    {
        if ($confirmed) {
            return self::OPTIONAL_FIELDS;
        }

        return array_values(array_unique(array_merge(
            self::REQUIRED_VENUE_FIELDS,
            self::REQUIRED_ADDRESS_FIELDS,
            self::OPTIONAL_FIELDS
        )));
    }

    public static function requiredVenueFields(): array
    {
        return self::REQUIRED_VENUE_FIELDS;
    }

    public static function requiredAddressFields(): array
    {
        return self::REQUIRED_ADDRESS_FIELDS;
    }
}
