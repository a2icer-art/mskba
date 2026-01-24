<?php

use App\Jobs\RunBookingPaymentExpiryJob;
use App\Jobs\RunBookingPendingExpiryJob;
use App\Jobs\RunContractExpiryJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bookings:expire', function () {
    RunBookingPaymentExpiryJob::dispatchSync();
})->purpose('Cancel expired bookings awaiting payment');

Artisan::command('bookings:pending-expire', function () {
    RunBookingPendingExpiryJob::dispatchSync();
})->purpose('Cancel pending bookings after review timeout');

Artisan::command('contracts:expire', function () {
    RunContractExpiryJob::dispatchSync();
})->purpose('Expire contracts that reached end date');

Schedule::job(new RunBookingPaymentExpiryJob())
    ->everyMinute()
    ->withoutOverlapping(1);

Schedule::job(new RunBookingPendingExpiryJob())
    ->everyMinute()
    ->withoutOverlapping(1);

Schedule::job(new RunContractExpiryJob())
    ->everyMinute()
    ->withoutOverlapping(1);
