<?php

namespace App\Support;

final class TimeValueNormalizer
{
    public static function toMinutes(?float $value, ?bool $isMinutes): int
    {
        if ($value === null) {
            return 0;
        }

        if ($isMinutes === false || $isMinutes === null) {
            return (int) round($value * 60);
        }

        return $value;
    }
}
