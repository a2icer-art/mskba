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
        App\Domain\Addresses\Models\Address::class,
        App\Domain\Venues\Models\VenueType::class,
        App\Domain\Metros\Models\Metro::class,
        App\Domain\Participants\Models\ParticipantRole::class,
        App\Domain\Participants\Models\ParticipantRoleAssignment::class,
        App\Domain\Events\Models\Event::class,
        App\Domain\Events\Models\EventBooking::class,
    ],
];
