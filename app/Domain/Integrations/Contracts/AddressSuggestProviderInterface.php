<?php

namespace App\Domain\Integrations\Contracts;

use App\Domain\Integrations\DTO\AddressSuggestion;

interface AddressSuggestProviderInterface
{
    /**
     * @return AddressSuggestion[]
     */
    public function suggest(string $query): array;
}
