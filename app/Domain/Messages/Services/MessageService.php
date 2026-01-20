<?php

namespace App\Domain\Messages\Services;

use App\Domain\Messages\Models\Conversation;
use App\Domain\Messages\Models\Message;
use App\Domain\Messages\Models\MessageReceipt;
use App\Domain\Messages\Services\MessageRealtimeService;
use App\Models\User;
use Illuminate\Support\Carbon;
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

        return $message;
    }

    public function sendSystem(
        Conversation $conversation,
        string $title,
        ?string $body,
        ?string $linkUrl = null,
        ?User $contactUser = null
    ): Message
    {
        $participants = $conversation->participants()->pluck('user_id')->all();
        if ($participants === []) {
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
        foreach ($participants as $userId) {
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
