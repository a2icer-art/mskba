<?php

namespace App\Domain\Balances\Enums;

enum BalanceTransactionType: string
{
    case TopUp = 'topup';
    case Hold = 'hold';
    case Release = 'release';
    case Debit = 'debit';
    case Block = 'block';
    case Unblock = 'unblock';
}
