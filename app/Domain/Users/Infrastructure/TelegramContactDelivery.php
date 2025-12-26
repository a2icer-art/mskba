<?php

namespace App\Domain\Users\Infrastructure;

use App\Domain\Users\Contracts\ContactDelivery;
use App\Domain\Users\Models\UserContact;

class TelegramContactDelivery implements ContactDelivery
{
    public function send(UserContact $contact, string $code): bool
    {
        return true;
    }
}
