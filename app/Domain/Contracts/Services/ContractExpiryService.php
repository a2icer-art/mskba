<?php

namespace App\Domain\Contracts\Services;

use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Contracts\Models\Contract;
use Illuminate\Support\Facades\Cache;

class ContractExpiryService
{
    private const THROTTLE_SECONDS = 60;
    private const THROTTLE_KEY = 'contract_expiry_throttle';
    private const BATCH_LIMIT = 100;

    public function runIfDue(): int
    {
        if (!Cache::add(self::THROTTLE_KEY, now()->timestamp, self::THROTTLE_SECONDS)) {
            return 0;
        }

        return $this->expireContracts();
    }

    private function expireContracts(): int
    {
        $now = now();

        return Contract::query()
            ->where('status', ContractStatus::Active->value)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $now)
            ->limit(self::BATCH_LIMIT)
            ->update([
                'status' => ContractStatus::Inactive->value,
                'updated_at' => $now,
            ]);
    }
}
