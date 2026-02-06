<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Enums\EventParticipantRole;
use App\Domain\Events\Enums\EventParticipantStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventParticipant;
use App\Domain\Messages\Services\ConversationService;
use App\Domain\Messages\Services\MessageService;
use App\Domain\Notifications\Enums\NotificationCode;
use App\Domain\Notifications\Services\NotificationDeliveryService;
use App\Domain\Notifications\Services\NotificationSettingsService;
use App\Models\User;

class EventParticipantNotificationService
{
    public function notifyInvited(EventParticipant $participant, User $inviter): void
    {
        $participant->loadMissing(['event:id,title,organizer_id', 'user:id,login', 'event.organizer:id,login']);
        $event = $participant->event;
        $recipient = $participant->user;

        if (!$event || !$recipient) {
            return;
        }

        $notificationCode = NotificationCode::EventParticipantInvited->value;
        $settingsService = app(NotificationSettingsService::class);
        if (!$settingsService->isEnabledForUser($recipient, $notificationCode)) {
            return;
        }

        $roleLabel = $this->roleLabel($participant->role);
        $bodyParts = ['Роль: ' . $roleLabel];
        if ($participant->status_change_reason) {
            $bodyParts[] = 'Комментарий: ' . $participant->status_change_reason;
        }
        $body = implode("\n", $bodyParts);

        $conversation = $this->resolveConversation($event, [$recipient->id]);
        $linkUrl = "/events/{$event->id}";

        app(MessageService::class)->sendSystem(
            $conversation,
            'Приглашение к участию в событии',
            $body,
            $linkUrl,
            $inviter,
            [$recipient->id]
        );
        app(NotificationDeliveryService::class)->sendExternal(
            $notificationCode,
            [$recipient],
            'Приглашение к участию в событии',
            $body,
            $linkUrl
        );
    }

    public function notifyResponse(EventParticipant $participant, User $actor): void
    {
        $participant->loadMissing(['event:id,title,organizer_id', 'user:id,login', 'inviter:id,login', 'event.organizer:id,login']);
        $event = $participant->event;
        if (!$event) {
            return;
        }

        $recipients = collect([$participant->inviter, $event->organizer])
            ->filter()
            ->unique('id')
            ->filter(fn (User $user) => $user->id !== $actor->id)
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        $notificationCode = NotificationCode::EventParticipantResponded->value;
        $settingsService = app(NotificationSettingsService::class);
        $recipients = $recipients
            ->filter(fn (User $user) => $settingsService->isEnabledForUser($user, $notificationCode))
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        $roleLabel = $this->roleLabel($participant->role);
        $statusLabel = $this->statusLabel($participant->status);
        $bodyParts = [
            'Участник: ' . ($participant->user?->login ?? '—'),
            'Роль: ' . $roleLabel,
            'Статус: ' . $statusLabel,
        ];
        if ($participant->user_status_reason) {
            $bodyParts[] = 'Комментарий: ' . $participant->user_status_reason;
        }
        $body = implode("\n", $bodyParts);

        $recipientIds = $recipients->pluck('id')->all();
        $conversation = $this->resolveConversation($event, $recipientIds);
        $linkUrl = "/events/{$event->id}";

        app(MessageService::class)->sendSystem(
            $conversation,
            'Ответ на приглашение в событие',
            $body,
            $linkUrl,
            $actor,
            $recipientIds
        );
        app(NotificationDeliveryService::class)->sendExternal(
            $notificationCode,
            $recipients->all(),
            'Ответ на приглашение в событие',
            $body,
            $linkUrl
        );
    }

    private function resolveConversation(Event $event, array $recipientIds)
    {
        $label = $event->title ? ('Событие — ' . $event->title) : ('Событие №' . $event->id);

        return app(ConversationService::class)->findOrCreateSystem(
            Event::class,
            $event->id,
            $label,
            $recipientIds
        );
    }

    private function roleLabel(?EventParticipantRole $role): string
    {
        return match ($role?->value) {
            EventParticipantRole::Player->value => 'Игрок',
            EventParticipantRole::Coach->value => 'Тренер',
            EventParticipantRole::Referee->value => 'Судья',
            EventParticipantRole::Media->value => 'Медиа',
            EventParticipantRole::Seller->value => 'Продавец',
            EventParticipantRole::Staff->value => 'Стафф',
            default => $role?->value ?? 'Роль',
        };
    }

    private function statusLabel(?EventParticipantStatus $status): string
    {
        return match ($status?->value) {
            EventParticipantStatus::Confirmed->value => 'Подтверждено',
            EventParticipantStatus::Reserve->value => 'Резерв',
            EventParticipantStatus::Declined->value => 'Отказ',
            EventParticipantStatus::Invited->value => 'Приглашен',
            EventParticipantStatus::Pending->value => 'На рассмотрении',
            default => $status?->value ?? 'Статус',
        };
    }
}
