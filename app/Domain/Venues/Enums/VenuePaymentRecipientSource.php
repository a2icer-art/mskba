<?php

namespace App\Domain\Venues\Enums;

enum VenuePaymentRecipientSource: string
{
    case Auto = 'auto';
    case Supervisor = 'supervisor';
    case Owner = 'owner';
    case Venue = 'venue';
}
