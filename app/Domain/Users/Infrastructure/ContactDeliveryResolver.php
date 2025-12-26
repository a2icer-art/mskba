<?php

namespace App\Domain\Users\Infrastructure;

use App\Domain\Users\Contracts\ContactDelivery;
use App\Domain\Users\Enums\ContactType;

class ContactDeliveryResolver
{
    public function resolve(ContactType $type): ContactDelivery
    {
        return match ($type) {
            ContactType::Email => new EmailContactDelivery(),
            ContactType::Phone => new PhoneContactDelivery(),
            ContactType::Telegram => new TelegramContactDelivery(),
            ContactType::Vk => new VkContactDelivery(),
            ContactType::Other => new OtherContactDelivery(),
        };
    }
}
