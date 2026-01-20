<?php

namespace App\Domain\Messages\Services;

use App\Domain\Messages\Events\UserConversationRead;
use App\Domain\Messages\Events\UserMessageCreated;
use App\Domain\Messages\Models\Conversation;
use App\Domain\Messages\Models\Message;
use App\Models\User;
use Illuminate\Support\Carbon;

class MessageRealtimeService
{
    public function broadcastMessage(Message $message): void
    {
        $conversation = $message->conversation;
        if (!$conversation) {
            return;
        }

        $conversation->loadMissing('participants.user');

        foreach ($conversation->participants as $participant) {
            $user = $participant->user;
            if (!$user) {
                continue;
            }
            event(new UserMessageCreated($user, $message));
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
            event(new UserConversationRead($user, $conversation, $reader, $timestamp));
        }
    }
}
