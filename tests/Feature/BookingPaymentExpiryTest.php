<?php

namespace Tests\Feature;

use App\Domain\Events\Enums\EventBookingModerationSource;
use App\Domain\Events\Enums\EventBookingStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventBooking;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Services\BookingNotificationService;
use App\Domain\Payments\Enums\PaymentCurrency;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Venues\Models\Venue;
use App\Domain\Venues\Models\VenueType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BookingPaymentExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_booking_is_cancelled_via_command(): void
    {
        $user = User::factory()->create();
        $venueType = VenueType::query()->create([
            'name' => 'Зал',
            'plural_name' => 'Залы',
            'alias' => 'hall',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $venue = Venue::query()->create([
            'name' => 'Тестовая площадка',
            'alias' => 'test-venue',
            'status' => 'confirmed',
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'confirmed_at' => now(),
            'confirmed_by' => $user->id,
            'venue_type_id' => $venueType->id,
        ]);
        $eventType = EventType::query()->create([
            'code' => 'test',
            'label' => 'Test',
        ]);
        $event = Event::query()->create([
            'event_type_id' => $eventType->id,
            'organizer_id' => $user->id,
            'status' => 'published',
            'title' => 'Test event',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'timezone' => 'UTC+3',
        ]);
        $booking = EventBooking::query()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => EventBookingStatus::AwaitingPayment,
            'payment_due_at' => now()->subMinute(),
            'created_by' => $user->id,
        ]);
        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'payable_type' => $booking->getMorphClass(),
            'payable_id' => $booking->id,
            'amount_minor' => 10000,
            'currency' => PaymentCurrency::Rub,
            'status' => PaymentStatus::Created,
        ]);

        $this->app->instance(BookingNotificationService::class, new class {
            public function notifyStatus(...$args): int
            {
                return 0;
            }
        });

        Artisan::call('bookings:expire');

        $booking->refresh();
        $payment->refresh();

        $this->assertSame(EventBookingStatus::Cancelled, $booking->status);
        $this->assertSame(EventBookingModerationSource::Auto, $booking->moderation_source);
        $this->assertNotNull($booking->moderated_at);
        $this->assertSame(PaymentStatus::Cancelled, $payment->status);
    }

    public function test_not_expired_booking_is_not_changed(): void
    {
        $user = User::factory()->create();
        $venueType = VenueType::query()->create([
            'name' => 'Зал',
            'plural_name' => 'Залы',
            'alias' => 'hall',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $venue = Venue::query()->create([
            'name' => 'Тестовая площадка',
            'alias' => 'test-venue',
            'status' => 'confirmed',
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'confirmed_at' => now(),
            'confirmed_by' => $user->id,
            'venue_type_id' => $venueType->id,
        ]);
        $eventType = EventType::query()->create([
            'code' => 'test',
            'label' => 'Test',
        ]);
        $event = Event::query()->create([
            'event_type_id' => $eventType->id,
            'organizer_id' => $user->id,
            'status' => 'published',
            'title' => 'Test event',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'timezone' => 'UTC+3',
        ]);
        $booking = EventBooking::query()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => EventBookingStatus::AwaitingPayment,
            'payment_due_at' => now()->addMinute(),
            'created_by' => $user->id,
        ]);
        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'payable_type' => $booking->getMorphClass(),
            'payable_id' => $booking->id,
            'amount_minor' => 10000,
            'currency' => PaymentCurrency::Rub,
            'status' => PaymentStatus::Created,
        ]);

        $this->app->instance(BookingNotificationService::class, new class {
            public function notifyStatus(...$args): int
            {
                return 0;
            }
        });

        Artisan::call('bookings:expire');

        $booking->refresh();
        $payment->refresh();

        $this->assertSame(EventBookingStatus::AwaitingPayment, $booking->status);
        $this->assertSame(PaymentStatus::Created, $payment->status);
    }
}
