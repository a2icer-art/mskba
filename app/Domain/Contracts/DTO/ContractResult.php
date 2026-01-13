<?php

namespace App\Domain\Contracts\DTO;

use App\Domain\Contracts\Models\Contract;

class ContractResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?Contract $contract = null,
        public readonly ?string $error = null,
    ) {
    }
}
