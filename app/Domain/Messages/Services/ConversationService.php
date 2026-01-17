<?php

namespace App\Domain\Messages\Services;

use App\Domain\Messages\Models\Conversation;
use App\Domain\Messages\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Support\Carbon;

class ConversationService
{
    public function findOrCreateDirect(User $first, User $second): Conversation
    {
        $conversation = Conversation::query()
            ->where('type', 'direct')
            ->whereHas('participants', fn ($query) => $query->where('user_id', $first->id))
            ->whereHas('participants', fn ($query) => $query->where('user_id', $second->id))
            ->withCount('participants')
            ->having('participants_count', 2)
            ->first();

        if ($conversation) {
            return $conversation;
        }

        $conversation = Conversation::query()->create([
            'type' => 'direct',
            'created_by' => $first->id,
        ]);

        $now = Carbon::now();
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $first->id,
            'joined_at' => $now,
        ]);
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $second->id,
            'joined_at' => $now,
        ]);

        return $conversation;
    }
}
