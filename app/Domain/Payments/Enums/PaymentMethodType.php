<?php

namespace App\Domain\Payments\Enums;

enum PaymentMethodType: string
{
    case Sbp = 'sbp';
    case Balance = 'balance';
    case Acquiring = 'acquiring';
}
