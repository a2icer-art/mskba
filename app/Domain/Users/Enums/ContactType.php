<?php

namespace App\Domain\Users\Enums;

enum ContactType: string
{
    case Email = 'email';
    case Phone = 'phone';
    case Telegram = 'telegram';
    case Vk = 'vk';
    case Other = 'other';
}
