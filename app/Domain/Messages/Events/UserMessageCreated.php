<?php

namespace App\Domain\Messages\Events;

use App\Domain\Messages\Models\Conversation;
use App\Domain\Messages\Models\Message;
use App\Domain\Messages\Services\MessageCountersService;
use App\Domain\Messages\Services\MessageQueryService;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public User $recipient,
        public Message $message
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->recipient->id)];
    }

    public function broadcastAs(): string
    {
        return 'messages.created';
    }

    public function broadcastWith(): array
    {
        $queryService = app(MessageQueryService::class);
        $countersService = app(MessageCountersService::class);

        $conversation = $this->message->conversation
            ?? Conversation::query()->whereKey($this->message->conversation_id)->first();

        return [
            'event' => 'messages.created',
            'message' => $queryService->presentMessage($this->message, $this->recipient),
            'conversation' => $conversation
                ? $queryService->presentConversation($conversation, $this->recipient)
                : null,
            'unread_messages' => $countersService->getUnreadMessages($this->recipient),
        ];
    }
}
