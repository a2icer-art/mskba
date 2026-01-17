<?php

namespace App\Domain\Events\Enums;

enum EventBookingModerationSource: string
{
    case Manual = 'manual';
    case Auto = 'auto';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Модератор',
            self::Auto => 'Автоматически',
        };
    }
}
