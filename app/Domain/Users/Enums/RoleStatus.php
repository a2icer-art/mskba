<?php

namespace App\Domain\Users\Enums;

enum RoleStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
