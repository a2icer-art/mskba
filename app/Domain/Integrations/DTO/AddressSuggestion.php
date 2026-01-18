<?php

namespace App\Domain\Integrations\DTO;

class AddressSuggestion
{
    public function __construct(
        public readonly string $label,
        public readonly ?string $country,
        public readonly ?string $city,
        public readonly ?string $street,
        public readonly ?string $building,
        public readonly array $metroNames = [],
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
    ) {
    }
}
