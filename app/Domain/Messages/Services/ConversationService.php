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

    public function findOrCreateSystem(string $contextType, int $contextId, string $label, array $userIds): Conversation
    {
        $conversation = Conversation::query()
            ->where('type', 'system')
            ->where('context_type', $contextType)
            ->where('context_id', $contextId)
            ->first();

        if (!$conversation) {
            $conversation = Conversation::query()->create([
                'type' => 'system',
                'context_type' => $contextType,
                'context_id' => $contextId,
                'context_label' => $label,
            ]);
        } elseif ($conversation->context_label !== $label) {
            $conversation->update(['context_label' => $label]);
        }

        $existingIds = $conversation->participants()->pluck('user_id')->all();
        $missingIds = array_values(array_diff($userIds, $existingIds));

        if ($missingIds !== []) {
            $now = Carbon::now();
            foreach ($missingIds as $userId) {
                ConversationParticipant::query()->create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                    'joined_at' => $now,
                ]);
            }
        }

        return $conversation;
    }
}
