<?php

namespace App\Domain\Notifications\Enums;

enum NotificationType: string
{
    case System = 'system';
    case Message = 'message';
}
