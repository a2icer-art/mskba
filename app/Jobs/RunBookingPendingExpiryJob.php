<?php

namespace App\Jobs;

use App\Domain\Events\Services\BookingPendingExpiryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunBookingPendingExpiryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(BookingPendingExpiryService $service): void
    {
        $service->runIfDue();
    }
}
