<?php

return [
    'address' => [
        'provider' => env('ADDRESS_SUGGEST_PROVIDER', 'yandex'),
        'default_city' => env('ADDRESS_DEFAULT_CITY', 'Москва'),
        'default_country' => env('ADDRESS_DEFAULT_COUNTRY', 'Россия'),
        'supported_cities' => [
            'Москва',
            'Химки',
            'Долгопрудный',
        ],
    ],
    'yandex' => [
        'api_key' => env('YANDEX_MAPS_API_KEY'),
    ],
];
