<?php

namespace App\Domain\Moderation\Rules;

use App\Domain\Moderation\Contracts\ModerationRulesContract;
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

        if (!$entity->contacts()->whereNotNull('confirmed_at')->exists()) {
            $missing[] = 'Нет подтвержденных контактов.';
        }

        $profile = $entity->profile;
        if (!$profile) {
            $missing[] = 'Не заполнен профиль пользователя.';
            return $missing;
        }

        if (!$profile->last_name) {
            $missing[] = 'Не заполнена фамилия.';
        }
        if (!$profile->first_name) {
            $missing[] = 'Не заполнено имя.';
        }
        if (!$profile->gender) {
            $missing[] = 'Не выбран пол.';
        }
        if (!$profile->birth_date) {
            $missing[] = 'Не указана дата рождения.';
        }

        return $missing;
    }
}
