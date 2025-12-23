<?php

namespace App\Domain\Users\Enums;

enum UserConfirmedBy: string
{
    case Admin = 'admin';
    case Email = 'email';
    case Phone = 'phone';
    case Telegram = 'telegram';
    case Vk = 'vk';
    case Other = 'other';
}
