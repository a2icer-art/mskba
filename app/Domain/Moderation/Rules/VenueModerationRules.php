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

        $venueLabels = [
            'name' => 'Не указано название площадки.',
            'venue_type_id' => 'Не указан тип площадки.',
        ];
        foreach (VenueModerationRequirements::requiredVenueFields() as $field) {
            if ($entity->{$field}) {
                continue;
            }

            $missing[] = $venueLabels[$field] ?? 'Не заполнено обязательное поле площадки.';
        }

        $address = $entity->latestAddress;
        if (!$address) {
            $missing[] = 'Не указан адрес площадки.';
        } else {
            $addressLabels = [
                'city' => 'Не указан город.',
                'street' => 'Не указана улица.',
                'building' => 'Не указан дом.',
            ];
            foreach (VenueModerationRequirements::requiredAddressFields() as $field) {
                if ($address->{$field}) {
                    continue;
                }

                $missing[] = $addressLabels[$field] ?? 'Не заполнено обязательное поле адреса.';
            }
        }

        if ($entity->status?->value === 'blocked') {
            $missing[] = 'Площадка заблокирована.';
        }

        return $missing;
    }
}
