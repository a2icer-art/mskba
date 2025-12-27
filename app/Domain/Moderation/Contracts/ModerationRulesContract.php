<?php

namespace App\Domain\Moderation\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

interface ModerationRulesContract
{
    /**
     * @return array<int, string>
     */
    public function getMissingRequirements(User $actor, Model $entity): array;
}
