<?php

namespace App\Domain\Moderation\Rules;

use App\Domain\Moderation\Contracts\ModerationRulesContract;
use App\Domain\Venues\Models\Venue;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class VenueModerationRules implements ModerationRulesContract
{
    public function getMissingRequirements(User $actor, Model $entity): array
    {
        if (!$entity instanceof Venue) {
            return ['Неверный тип сущности для модерации.'];
        }

        $missing = [];

        if ($entity->created_by && $actor->id !== $entity->created_by) {
            $missing[] = 'Запрос может отправить только владелец площадки.';
        }

        if (!$entity->name) {
            $missing[] = 'Не указано название площадки.';
        }

        if (!$entity->venue_type_id) {
            $missing[] = 'Не указан тип площадки.';
        }

        if (!$entity->address) {
            $missing[] = 'Не указан адрес площадки.';
        }

        return $missing;
    }
}
