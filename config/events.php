<?php

return [
    'lead_time_minutes' => 15,
    'min_duration_minutes' => 15,
    'event_type_rules' => [
        'game' => [
            'limit_role' => 'player',
            'allowed_roles' => ['player', 'coach', 'referee', 'media', 'seller', 'staff'],
        ],
        'game_training' => [
            'limit_role' => 'player',
            'allowed_roles' => ['player', 'coach', 'referee', 'media', 'seller', 'staff'],
        ],
        'training' => [
            'limit_role' => 'player',
            'allowed_roles' => ['player', 'coach', 'referee', 'media', 'seller', 'staff'],
        ],
    ],
    'paid_roles' => ['referee', 'coach', 'media', 'staff'],
];
