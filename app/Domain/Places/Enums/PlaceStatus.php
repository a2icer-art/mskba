<?php

namespace App\Domain\Places\Enums;

enum PlaceStatus: string
{
    case Unconfirmed = 'unconfirmed';
    case Moderation = 'moderation';
    case Confirmed = 'confirmed';
}
