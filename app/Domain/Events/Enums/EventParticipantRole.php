<?php

namespace App\Domain\Events\Enums;

enum EventParticipantRole: string
{
    case Player = 'player';
    case Coach = 'coach';
    case Referee = 'referee';
    case Media = 'media';
    case Seller = 'seller';
    case Staff = 'staff';

    public static function values(): array
    {
        return array_map(static fn (self $item) => $item->value, self::cases());
    }
}
