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

    public const OPTIONAL_VENUE_FIELDS = [
        'commentary',
        'str_address',
    ];

    public const OPTIONAL_ADDRESS_FIELDS = [
        'metro_id',
    ];

    public static function editableFields(bool $confirmed): array
    {
        if ($confirmed) {
            return array_values(array_unique(array_merge(
                self::OPTIONAL_VENUE_FIELDS,
                ['name']
            )));
        }

        return array_values(array_unique(array_merge(
            self::REQUIRED_VENUE_FIELDS,
            self::REQUIRED_ADDRESS_FIELDS,
            self::OPTIONAL_VENUE_FIELDS,
            self::OPTIONAL_ADDRESS_FIELDS
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
