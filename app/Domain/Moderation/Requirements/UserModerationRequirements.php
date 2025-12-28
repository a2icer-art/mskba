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
}
