<?php

namespace App\Domain\Contracts\Enums;

enum ContractType: string
{
    case Creator = 'creator';
    case Owner = 'owner';
    case Supervisor = 'supervisor';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Creator => 'Создатель',
            self::Owner => 'Владелец',
            self::Supervisor => 'Супервайзер',
            self::Employee => 'Сотрудник',
        };
    }

    public function level(): int
    {
        return match ($this) {
            self::Creator => 50,
            self::Owner => 40,
            self::Supervisor => 30,
            self::Employee => 20,
        };
    }
}
