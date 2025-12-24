<?php

namespace App\Domain\Venues\Enums;

enum VenueStatus: string
{
    case Unconfirmed = 'unconfirmed';
    case Moderation = 'moderation';
    case Confirmed = 'confirmed';
}
