<?php

namespace App\Domain\Messages\Services;

use App\Domain\Messages\Events\UserConversationRead;
use App\Domain\Messages\Events\UserMessageCreated;
use App\Domain\Messages\Models\Conversation;
use App\Domain\Messages\Models\Message;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class MessageRealtimeService
{
    public function broadcastMessage(Message $message): void
    {
        $conversation = $message->relationLoaded('conversation')
            ? $message->conversation
            : Conversation::query()->whereKey($message->conversation_id)->first();
        if (!$conversation) {
            return;
        }

        $conversation->loadMissing('participants.user');

        foreach ($conversation->participants as $participant) {
            $user = $participant->user;
            if (!$user) {
                continue;
            }
            try {
                event(new UserMessageCreated($user, $message));
            } catch (\Throwable $error) {
                Log::warning('Message broadcast failed.', [
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                    'user_id' => $user->id,
                    'error' => $error->getMessage(),
                ]);
            }
        }
    }

    public function broadcastConversationRead(Conversation $conversation, User $reader, ?Carbon $readAt = null): void
    {
        $conversation->loadMissing('participants.user');

        $timestamp = $readAt ?? Carbon::now();
        foreach ($conversation->participants as $participant) {
            $user = $participant->user;
            if (!$user) {
                continue;
            }
            try {
                event(new UserConversationRead($user, $conversation, $reader, $timestamp));
            } catch (\Throwable $error) {
                Log::warning('Conversation read broadcast failed.', [
                    'conversation_id' => $conversation->id,
                    'reader_id' => $reader->id,
                    'user_id' => $user->id,
                    'error' => $error->getMessage(),
                ]);
            }
        }
    }
}
