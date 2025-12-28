<?php

namespace App\Domain\Moderation\Rules;

use App\Domain\Moderation\Contracts\ModerationRulesContract;
use App\Domain\Moderation\Requirements\UserModerationRequirements;
use App\Domain\Users\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserModerationRules implements ModerationRulesContract
{
    public function getMissingRequirements(User $actor, Model $entity): array
    {
        if (!$entity instanceof User) {
            return ['Неверный тип сущности для модерации.'];
        }

        $missing = [];

        if ($actor->id !== $entity->id) {
            $missing[] = 'Запрос может отправить только владелец аккаунта.';
        }

        if ($entity->status === UserStatus::Confirmed) {
            $missing[] = 'Пользователь уже подтвержден.';
        }

        if ($entity->status === UserStatus::Blocked) {
            $missing[] = 'Пользователь заблокирован.';
        }

        if (!$entity->contacts()->whereNotNull('confirmed_at')->exists()) {
            $missing[] = 'Нет подтвержденных контактов.';
        }

        $profile = $entity->profile;
        if (!$profile) {
            $missing[] = 'Не заполнен профиль пользователя.';
            return $missing;
        }

        $labels = [
            'last_name' => 'Не заполнена фамилия.',
            'first_name' => 'Не заполнено имя.',
            'gender' => 'Не выбран пол.',
            'birth_date' => 'Не указана дата рождения.',
        ];

        foreach (UserModerationRequirements::REQUIRED_PROFILE_FIELDS as $field) {
            if ($profile->{$field}) {
                continue;
            }

            $missing[] = $labels[$field] ?? 'Не заполнено обязательное поле профиля.';
        }

        return $missing;
    }
}
