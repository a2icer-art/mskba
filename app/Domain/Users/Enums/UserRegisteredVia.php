<?php

namespace App\Domain\Users\Enums;

enum UserRegisteredVia: string
{
    case Site = 'site';
    case TgLink = 'tg_link';
    case EmailLink = 'email_link';
    case Other = 'other';
}
