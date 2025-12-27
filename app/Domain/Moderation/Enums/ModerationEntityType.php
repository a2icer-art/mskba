<?php

namespace App\Domain\Moderation\Enums;

enum ModerationEntityType: string
{
    case User = 'user';
    case Venue = 'venue';
    case Event = 'event';
}
