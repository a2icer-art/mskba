<?php

namespace App\Domain\Balances\Enums;

enum BalanceStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
}
