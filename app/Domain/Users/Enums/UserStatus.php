<?php

namespace App\Domain\Users\Enums;

enum UserStatus: string
{
    case Unconfirmed = 'unconfirmed';
    case Confirmed = 'confirmed';
}
