<?php

namespace App\Domain\Venues\Enums;

enum VenuePaymentOrder: string
{
    case Prepayment = 'prepayment';
    case PartialPrepayment = 'partial_prepayment';
    case Postpayment = 'postpayment';
}
