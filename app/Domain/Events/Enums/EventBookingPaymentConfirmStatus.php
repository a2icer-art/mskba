<?php

namespace App\Domain\Events\Enums;

enum EventBookingPaymentConfirmStatus: string
{
    case None = 'none';
    case UserPaidPending = 'user_paid_pending';
    case UserPaidRejected = 'user_paid_rejected';
    case AdminConfirmed = 'admin_confirmed';
}
