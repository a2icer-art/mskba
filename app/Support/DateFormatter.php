<?php

namespace App\Support;

use DateTimeInterface;

class DateFormatter
{
    public static function dateTime(?DateTimeInterface $value): ?string
    {
        return $value?->format('Y-m-d H:i:s');
    }

    public static function date(?DateTimeInterface $value): ?string
    {
        return $value?->format('Y-m-d');
    }
}
