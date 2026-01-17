<?php

namespace App\Domain\Messages\Services;

use App\Domain\Messages\Enums\MessagePrivacyMode;
use App\Domain\Messages\Models\MessageAllowList;
use App\Domain\Messages\Models\MessageBlockList;
use App\Domain\Messages\Models\MessagePrivacySetting;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class MessagePrivacyService
{
    public function getSettings(User $user): MessagePrivacySetting
    {
        return MessagePrivacySetting::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['mode' => MessagePrivacyMode::All->value]
        );
    }

    public function ensureCanSend(User $sender, User $recipient): void
    {
        if ($sender->id === $recipient->id) {
            throw ValidationException::withMessages([
                'recipient_id' => 'Нельзя отправлять сообщения самому себе.',
            ]);
        }

        $recipientBlocked = MessageBlockList::query()
            ->where('owner_id', $recipient->id)
            ->where('blocked_user_id', $sender->id)
            ->exists();
        if ($recipientBlocked) {
            throw ValidationException::withMessages([
                'recipient_id' => 'Пользователь запретил получать сообщения от вас.',
            ]);
        }

        $senderBlocked = MessageBlockList::query()
            ->where('owner_id', $sender->id)
            ->where('blocked_user_id', $recipient->id)
            ->exists();
        if ($senderBlocked) {
            throw ValidationException::withMessages([
                'recipient_id' => 'Вы заблокировали этого пользователя.',
            ]);
        }

        $settings = $this->getSettings($recipient);
        $mode = $settings->mode ?? MessagePrivacyMode::All;

        if ($mode === MessagePrivacyMode::None) {
            throw ValidationException::withMessages([
                'recipient_id' => 'Пользователь не принимает личные сообщения.',
            ]);
        }

        if ($mode === MessagePrivacyMode::Groups) {
            throw ValidationException::withMessages([
                'recipient_id' => 'Отправка сообщений разрешена только для групп (в разработке).',
            ]);
        }

        if ($mode === MessagePrivacyMode::AllowList) {
            $isAllowed = MessageAllowList::query()
                ->where('owner_id', $recipient->id)
                ->where('allowed_user_id', $sender->id)
                ->exists();

            if (!$isAllowed) {
                throw ValidationException::withMessages([
                    'recipient_id' => 'Пользователь принимает сообщения только от выбранных пользователей.',
                ]);
            }
        }
    }
}
