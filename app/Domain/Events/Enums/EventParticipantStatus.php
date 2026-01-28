<?php

namespace App\Domain\Events\Enums;

enum EventParticipantStatus: string
{
    case Invited = 'invited';
    case Confirmed = 'confirmed';
    case Reserve = 'reserve';
    case Declined = 'declined';

    public static function values(): array
    {
        return array_map(static fn (self $item) => $item->value, self::cases());
    }
}
