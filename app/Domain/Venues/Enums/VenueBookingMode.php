<?php

namespace App\Domain\Venues\Enums;

enum VenueBookingMode: string
{
    case Instant = 'instant';
    case ApprovalRequired = 'approval_required';
}
