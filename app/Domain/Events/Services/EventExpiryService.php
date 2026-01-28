<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\Event;
use Illuminate\Support\Facades\Cache;

class EventExpiryService
{
    private const THROTTLE_SECONDS = 60;
    private const THROTTLE_KEY = 'event_expiry_throttle';
    private const BATCH_LIMIT = 100;

    public function runIfDue(): int
    {
        if (!Cache::add(self::THROTTLE_KEY, now()->timestamp, self::THROTTLE_SECONDS)) {
            return 0;
        }

        return $this->expireEvents();
    }

    private function expireEvents(): int
    {
        $now = now();

        return Event::query()
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $now)
            ->where('status', '!=', 'expired')
            ->limit(self::BATCH_LIMIT)
            ->update([
                'status' => 'expired',
                'updated_at' => $now,
            ]);
    }
}
