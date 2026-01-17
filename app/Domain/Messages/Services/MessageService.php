<?php

namespace App\Domain\Messages\Services;

use App\Domain\Messages\Models\Conversation;
use App\Domain\Messages\Models\Message;
use App\Domain\Messages\Models\MessageReceipt;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class MessageService
{
    public function send(Conversation $conversation, User $sender, string $body): Message
    {
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

        return $message;
    }
}
