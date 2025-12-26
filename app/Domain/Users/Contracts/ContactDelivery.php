<?php

namespace App\Domain\Users\Contracts;

use App\Domain\Users\Models\UserContact;

interface ContactDelivery
{
    public function send(UserContact $contact, string $code): bool;
}
