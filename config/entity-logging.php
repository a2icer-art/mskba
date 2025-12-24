<?php

return [
    'enabled' => true,
    'ignore_console' => true,
    'ignored_attributes' => [
        'created_at',
        'updated_at',
        'deleted_at',
    ],
    'loggable' => [
        App\Models\User::class,
        App\Domain\Users\Models\UserProfile::class,
        App\Domain\Users\Models\Role::class,
        App\Domain\Users\Models\UserRole::class,
        App\Domain\Venues\Models\Venue::class,
        App\Domain\Venues\Models\VenueType::class,
        App\Domain\Participants\Models\ParticipantRole::class,
        App\Domain\Participants\Models\ParticipantRoleAssignment::class,
    ],
];
