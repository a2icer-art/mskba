<?php

namespace App\Domain\Moderation\Rules;

use App\Domain\Moderation\Contracts\ModerationRulesContract;
use App\Domain\Moderation\Requirements\VenueModerationRequirements;
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

        $labels = [
            'name' => 'Не указано название площадки.',
            'venue_type_id' => 'Не указан тип площадки.',
            'address' => 'Не указан адрес площадки.',
        ];

        foreach (VenueModerationRequirements::REQUIRED_FIELDS as $field) {
            if ($entity->{$field}) {
                continue;
            }

            $missing[] = $labels[$field] ?? 'Не заполнено обязательное поле площадки.';
        }

        if ($entity->status?->value === 'blocked') {
            $missing[] = 'Площадка заблокирована.';
        }

        return $missing;
    }
}
