<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Models\Notification;
use App\Models\User;

class NotificationService
{
    public function createSystem(
        User $recipient,
        string $title,
        ?string $body = null,
        ?string $linkUrl = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?User $actor = null,
        ?User $contactUser = null
    ): Notification {
        return Notification::query()->create([
            'recipient_id' => $recipient->id,
            'actor_id' => $actor?->id,
            'contact_user_id' => $contactUser?->id,
            'type' => NotificationType::System,
            'title' => $title,
            'body' => $body,
            'link_url' => $linkUrl,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
    }
}
