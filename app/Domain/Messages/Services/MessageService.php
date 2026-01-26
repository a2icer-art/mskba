<?php

namespace App\Domain\Messages\Services;

use App\Domain\Messages\Models\Conversation;
use App\Domain\Messages\Models\Message;
use App\Domain\Messages\Models\MessageReceipt;
use App\Domain\Messages\Services\MessageRealtimeService;
use App\Domain\Notifications\Enums\NotificationCode;
use App\Domain\Notifications\Services\NotificationDeliveryService;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MessageService
{
    public function send(Conversation $conversation, User $sender, string $body): Message
    {
        if ($conversation->type === 'system') {
            throw ValidationException::withMessages([
                'message' => 'Нельзя отвечать на системные уведомления. Выберите контакт для диалога.',
            ]);
        }

        $participants = $conversation->participants()->pluck('user_id')->all();
        if (!in_array($sender->id, $participants, true)) {
            throw ValidationException::withMessages([
                'message' => 'Нельзя отправлять сообщения в этот диалог.',
            ]);
        }

        $message = $conversation->messages()->create([
            'sender_id' => $sender->id,
            'body' => $body,
        ]);

        $now = Carbon::now();
        foreach ($participants as $userId) {
            MessageReceipt::query()->create([
                'message_id' => $message->id,
                'user_id' => $userId,
                'read_at' => $userId === $sender->id ? $now : null,
            ]);
        }

        $conversation->update(['updated_at' => $now]);
        app(MessageRealtimeService::class)->broadcastMessage($message);
        $recipientIds = array_values(array_filter(
            $participants,
            fn (int $userId) => $userId !== $sender->id
        ));
        if ($recipientIds !== []) {
            $recipients = User::query()
                ->whereIn('id', $recipientIds)
                ->get(['id', 'login']);

            if ($recipients->isNotEmpty()) {
                $title = 'Новое сообщение от ' . $sender->login;
                $body = Str::limit(trim($body), 100, '...');
                $linkUrl = "/account/messages?conversation={$conversation->id}";

                app(NotificationDeliveryService::class)->sendExternal(
                    NotificationCode::MessageCreated->value,
                    $recipients->all(),
                    $title,
                    $body,
                    $linkUrl
                );
            }
        }

        return $message;
    }

    public function sendSystem(
        Conversation $conversation,
        string $title,
        ?string $body,
        ?string $linkUrl = null,
        ?User $contactUser = null,
        ?array $recipientIds = null
    ): Message
    {
        $participants = $conversation->participants()->pluck('user_id')->all();
        $recipients = $recipientIds ?? $participants;
        if ($recipients === []) {
            throw ValidationException::withMessages([
                'message' => 'Нельзя отправлять сообщения без участников.',
            ]);
        }

        $message = $conversation->messages()->create([
            'sender_id' => null,
            'contact_user_id' => $contactUser?->id,
            'title' => $title,
            'body' => $body ?? '',
            'link_url' => $linkUrl,
        ]);

        $now = Carbon::now();
        foreach ($recipients as $userId) {
            MessageReceipt::query()->create([
                'message_id' => $message->id,
                'user_id' => $userId,
                'read_at' => null,
            ]);
        }

        $conversation->update(['updated_at' => $now]);
        app(MessageRealtimeService::class)->broadcastMessage($message);

        return $message;
    }
}
