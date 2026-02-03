<?php

namespace App\Domain\Events\Enums;

enum EventBookingPaymentConfirmationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
