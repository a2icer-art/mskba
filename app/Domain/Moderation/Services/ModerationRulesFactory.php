<?php

namespace App\Domain\Moderation\Services;

use App\Domain\Moderation\Contracts\ModerationRulesContract;
use App\Domain\Moderation\Enums\ModerationEntityType;
use App\Domain\Moderation\Rules\EmptyModerationRules;
use App\Domain\Moderation\Rules\UserModerationRules;

class ModerationRulesFactory
{
    public function make(ModerationEntityType $entityType): ModerationRulesContract
    {
        return match ($entityType) {
            ModerationEntityType::User => app(UserModerationRules::class),
            default => app(EmptyModerationRules::class),
        };
    }
}
