<?php

use App\Jobs\RunBookingPaymentExpiryJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bookings:expire', function () {
    RunBookingPaymentExpiryJob::dispatchSync();
})->purpose('Cancel expired bookings awaiting payment');

Schedule::job(new RunBookingPaymentExpiryJob())
    ->everyMinute()
    ->withoutOverlapping(1);
