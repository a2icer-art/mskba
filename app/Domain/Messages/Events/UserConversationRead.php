<?php

namespace App\Domain\Messages\Events;

use App\Domain\Messages\Models\Conversation;
use App\Domain\Messages\Services\MessageCountersService;
use App\Domain\Messages\Services\MessageQueryService;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class UserConversationRead implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public User $recipient,
        public Conversation $conversation,
        public User $reader,
        public Carbon $readAt
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->recipient->id)];
    }

    public function broadcastAs(): string
    {
        return 'messages.read';
    }

    public function broadcastWith(): array
    {
        $queryService = app(MessageQueryService::class);
        $countersService = app(MessageCountersService::class);

        return [
            'event' => 'messages.read',
            'conversation' => $queryService->presentConversation($this->conversation, $this->recipient),
            'conversation_id' => $this->conversation->id,
            'reader_id' => $this->reader->id,
            'read_at' => $this->readAt->toDateTimeString(),
            'unread_messages' => $countersService->getUnreadMessages($this->recipient),
        ];
    }
}
