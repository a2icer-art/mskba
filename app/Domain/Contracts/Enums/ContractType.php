<?php

namespace App\Domain\Contracts\Enums;

enum ContractType: string
{
    case Creator = 'creator';
    case Owner = 'owner';
    case Manager = 'manager';
    case Controller = 'controller';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Creator => 'Создатель',
            self::Owner => 'Владелец',
            self::Manager => 'Менеджер',
            self::Controller => 'Контроллер',
            self::Employee => 'Сотрудник',
        };
    }

    public function level(): int
    {
        return match ($this) {
            self::Creator => 50,
            self::Owner => 40,
            self::Manager => 30,
            self::Controller, self::Employee => 20,
        };
    }
}
