<?php

namespace App\Domain\Contracts\Enums;

enum ContractStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
