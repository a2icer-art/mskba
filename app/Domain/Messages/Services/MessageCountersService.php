<?php

namespace App\Domain\Messages\Services;

use App\Domain\Messages\Models\MessageReceipt;
use App\Models\User;

class MessageCountersService
{
    public function getUnreadMessages(User $user): int
    {
        return MessageReceipt::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->whereNull('deleted_at')
            ->count();
    }
}
