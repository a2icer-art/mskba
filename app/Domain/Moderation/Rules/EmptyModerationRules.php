<?php

namespace App\Domain\Moderation\Rules;

use App\Domain\Moderation\Contracts\ModerationRulesContract;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class EmptyModerationRules implements ModerationRulesContract
{
    public function getMissingRequirements(User $actor, Model $entity): array
    {
        return [];
    }
}
