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
            $unread = $unreadCounts[$conversation->id] ?? 0;

            return $this->presentConversation($conversation, $user, (int) $unread);
        })->all();
    }

    public function getMessages(
        Conversation $conversation,
        User $user,
        int $limit = 10,
        ?int $beforeId = null,
        ?int $afterId = null
    ): array
    {
        $query = Message::query()
            ->where('conversation_id', $conversation->id)
            ->whereHas('receipts', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->whereNull('deleted_at');
            })
            ->with([
                'sender:id,login',
                'contactUser:id,login',
                'receipts' => fn ($query) => $query->whereNull('deleted_at'),
            ])
            ->when($beforeId, fn ($q) => $q->where('id', '<', $beforeId))
            ->when($afterId, fn ($q) => $q->where('id', '>', $afterId))
            ->orderByDesc('id')
            ->limit($limit);

        $messages = $query->get()->reverse()->values();

        $payload = $messages->map(function (Message $message) use ($user): array {
            $receipt = $message->receipts->firstWhere('user_id', $user->id);
            $readByOthersAt = $message->receipts
                ->filter(fn ($item) => $item->user_id !== $user->id && $item->read_at)
                ->max('read_at');

            return [
                'id' => $message->id,
                'title' => $message->title,
                'body' => $message->body,
                'link_url' => $message->link_url,
                'contact_user' => $message->contactUser
                    ? [
                        'id' => $message->contactUser->id,
                        'login' => $message->contactUser->login,
                    ]
                    : null,
                'created_at' => $message->created_at?->toDateTimeString(),
                'read_at' => $receipt?->read_at?->toDateTimeString(),
                'read_by_others_at' => $readByOthersAt?->toDateTimeString(),
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

        $oldestId = $messages->first()?->id;
        if ($afterId) {
            return [
                'messages' => $payload,
                'meta' => null,
            ];
        }
        if (!$oldestId) {
            return [
                'messages' => $payload,
                'meta' => [
                    'has_more' => false,
                    'oldest_id' => null,
                ],
            ];
        }

        $hasMore = Message::query()
            ->where('conversation_id', $conversation->id)
            ->whereHas('receipts', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->whereNull('deleted_at');
            })
            ->where('id', '<', $oldestId)
            ->exists();

        return [
            'messages' => $payload,
            'meta' => [
                'has_more' => $hasMore,
                'oldest_id' => $oldestId,
            ],
        ];
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

    public function presentConversation(Conversation $conversation, User $user, ?int $unreadCount = null): array
    {
        $conversation->loadMissing(['participants.user:id,login', 'lastMessage.sender:id,login']);

        $otherParticipant = $conversation->participants
            ->first(fn ($participant) => $participant->user_id !== $user->id);
        $otherUser = $otherParticipant?->user;
        $lastMessage = $conversation->lastMessage;
        $isSystem = $conversation->type === 'system';
        $title = $isSystem ? ($conversation->context_label ?? 'Системное уведомление') : ($otherUser?->login ?? 'Диалог');
        $lastPreview = $lastMessage?->title ?: $lastMessage?->body;

        if ($unreadCount === null) {
            $unreadCount = MessageReceipt::query()
                ->join('messages', 'message_receipts.message_id', '=', 'messages.id')
                ->where('message_receipts.user_id', $user->id)
                ->whereNull('message_receipts.read_at')
                ->whereNull('message_receipts.deleted_at')
                ->where('messages.conversation_id', $conversation->id)
                ->count();
        }

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'title' => $title,
            'other_user' => $otherUser
                ? [
                    'id' => $otherUser->id,
                    'login' => $otherUser->login,
                ]
                : null,
            'last_message' => $lastMessage
                ? [
                    'id' => $lastMessage->id,
                    'body' => $lastPreview,
                    'created_at' => $lastMessage->created_at?->toDateTimeString(),
                    'sender' => $lastMessage->sender
                        ? [
                            'id' => $lastMessage->sender->id,
                            'login' => $lastMessage->sender->login,
                        ]
                        : null,
                ]
                : null,
            'unread_count' => (int) $unreadCount,
            'updated_at' => $conversation->updated_at?->toDateTimeString(),
        ];
    }

    public function presentMessage(Message $message, User $user): array
    {
        $message->loadMissing([
            'sender:id,login',
            'contactUser:id,login',
            'receipts' => fn ($query) => $query->whereNull('deleted_at'),
        ]);

        $receipt = $message->receipts->firstWhere('user_id', $user->id);
        $readByOthersAt = $message->receipts
            ->filter(fn ($item) => $item->user_id !== $user->id && $item->read_at)
            ->max('read_at');

        return [
            'id' => $message->id,
            'title' => $message->title,
            'body' => $message->body,
            'link_url' => $message->link_url,
            'contact_user' => $message->contactUser
                ? [
                    'id' => $message->contactUser->id,
                    'login' => $message->contactUser->login,
                ]
                : null,
            'created_at' => $message->created_at?->toDateTimeString(),
            'read_at' => $receipt?->read_at?->toDateTimeString(),
            'read_by_others_at' => $readByOthersAt?->toDateTimeString(),
            'sender' => $message->sender
                ? [
                    'id' => $message->sender->id,
                    'login' => $message->sender->login,
                ]
                : null,
            'is_outgoing' => $message->sender_id === $user->id,
            'is_read' => (bool) $receipt?->read_at,
        ];
    }
}
