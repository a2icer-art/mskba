<?php

namespace App\Domain\Messages\Services;

use App\Domain\Messages\Models\Conversation;
use App\Domain\Messages\Models\ConversationParticipant;
use App\Domain\Messages\Models\Message;
use App\Domain\Messages\Models\MessageReceipt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MessageQueryService
{
    public function getConversations(User $user): array
    {
        $conversationIds = ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->pluck('conversation_id')
            ->all();

        if ($conversationIds === []) {
            return [];
        }

        $unreadCounts = MessageReceipt::query()
            ->select('messages.conversation_id', DB::raw('count(*) as unread_count'))
            ->join('messages', 'message_receipts.message_id', '=', 'messages.id')
            ->where('message_receipts.user_id', $user->id)
            ->whereNull('message_receipts.read_at')
            ->whereNull('message_receipts.deleted_at')
            ->whereIn('messages.conversation_id', $conversationIds)
            ->groupBy('messages.conversation_id')
            ->pluck('unread_count', 'messages.conversation_id')
            ->all();

        $conversations = Conversation::query()
            ->whereIn('id', $conversationIds)
            ->with(['participants.user:id,login', 'lastMessage.sender:id,login'])
            ->orderByDesc('updated_at')
            ->get();

        return $conversations->map(function (Conversation $conversation) use ($user, $unreadCounts): array {
            $otherParticipant = $conversation->participants
                ->first(fn ($participant) => $participant->user_id !== $user->id);
            $otherUser = $otherParticipant?->user;
            $lastMessage = $conversation->lastMessage;
            $unread = $unreadCounts[$conversation->id] ?? 0;

            return [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'title' => $otherUser?->login ?? 'Диалог',
                'other_user' => $otherUser
                    ? [
                        'id' => $otherUser->id,
                        'login' => $otherUser->login,
                    ]
                    : null,
                'last_message' => $lastMessage
                    ? [
                        'id' => $lastMessage->id,
                        'body' => $lastMessage->body,
                        'created_at' => $lastMessage->created_at?->toDateTimeString(),
                        'sender' => $lastMessage->sender
                            ? [
                                'id' => $lastMessage->sender->id,
                                'login' => $lastMessage->sender->login,
                            ]
                            : null,
                    ]
                    : null,
                'unread_count' => (int) $unread,
                'updated_at' => $conversation->updated_at?->toDateTimeString(),
            ];
        })->all();
    }

    public function getMessages(Conversation $conversation, User $user): array
    {
        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->whereHas('receipts', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->whereNull('deleted_at');
            })
            ->with([
                'sender:id,login',
                'receipts' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->orderBy('id')
            ->get();

        return $messages->map(function (Message $message) use ($user): array {
            $receipt = $message->receipts->first();

            return [
                'id' => $message->id,
                'body' => $message->body,
                'created_at' => $message->created_at?->toDateTimeString(),
                'sender' => $message->sender
                    ? [
                        'id' => $message->sender->id,
                        'login' => $message->sender->login,
                    ]
                    : null,
                'is_outgoing' => $message->sender_id === $user->id,
                'is_read' => (bool) $receipt?->read_at,
            ];
        })->all();
    }

    public function markConversationRead(Conversation $conversation, User $user): int
    {
        $messageIds = Message::query()
            ->where('conversation_id', $conversation->id)
            ->pluck('id')
            ->all();

        if ($messageIds === []) {
            return 0;
        }

        return MessageReceipt::query()
            ->where('user_id', $user->id)
            ->whereIn('message_id', $messageIds)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function deleteMessage(Message $message, User $user): bool
    {
        return MessageReceipt::query()
            ->where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]) > 0;
    }
}
