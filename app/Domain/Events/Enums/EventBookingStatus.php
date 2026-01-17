<?php

namespace App\Domain\Events\Enums;

enum EventBookingStatus: string
{
    case Pending = 'pending';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Approved = 'approved';
    case Cancelled = 'cancelled';
}
