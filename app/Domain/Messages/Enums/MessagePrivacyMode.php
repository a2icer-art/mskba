<?php

namespace App\Domain\Messages\Enums;

enum MessagePrivacyMode: string
{
    case None = 'none';
    case All = 'all';
    case Groups = 'groups';
    case AllowList = 'allow_list';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Никто',
            self::All => 'Все',
            self::Groups => 'Группы',
            self::AllowList => 'Только выбранные пользователи',
        };
    }
}
