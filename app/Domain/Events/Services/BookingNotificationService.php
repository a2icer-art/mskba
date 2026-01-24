<?php

namespace App\Domain\Events\Services;

use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Events\Enums\EventBookingStatus;
use App\Domain\Events\Models\EventBooking;
use App\Domain\Messages\Services\ConversationService;
use App\Domain\Messages\Services\MessageService;
use App\Domain\Permissions\Enums\PermissionCode;
use App\Models\User;

class BookingNotificationService
{
    public function notifyStatus(EventBooking $booking, EventBookingStatus $status, ?User $actor = null): int
    {
        $booking->loadMissing([
            'event:id',
            'venue:id,alias,venue_type_id',
            'venue.venueType:id,alias',
            'creator:id,login',
            'payment:id,payment_code',
            'paymentOrder:id,code',
        ]);

        $title = $this->titleForStatus($status);
        $body = $booking->moderation_comment ?: null;
        return $this->sendSystemMessage($booking, $title, $body, $actor);
    }

    public function notifyPendingWarning(EventBooking $booking, int $minutes): int
    {
        $booking->loadMissing([
            'event:id',
            'venue:id,alias,venue_type_id',
            'venue.venueType:id,alias',
            'creator:id,login',
            'payment:id,payment_code',
            'paymentOrder:id,code',
        ]);

        $title = 'Заявка на бронирование скоро будет отменена';
        $body = 'Заявка будет автоматически отменена через ' . $minutes . ' мин., если не будет подтверждена.';

        return $this->sendSystemMessage($booking, $title, $body, null);
    }

    private function resolveBookingCode(EventBooking $booking): ?string
    {
        return $booking->payment?->payment_code
            ?? ($booking->id ? (string) $booking->id : null);
    }

    private function resolveVenueRecipients(EventBooking $booking): array
    {
        $venue = $booking->venue;
        if (!$venue) {
            return [];
        }

        $permissionCodes = [
            PermissionCode::VenueBookingConfirm->value,
            PermissionCode::VenueBookingCancel->value,
        ];

        return Contract::query()
            ->where('entity_type', $venue->getMorphClass())
            ->where('entity_id', $venue->id)
            ->where('status', ContractStatus::Active->value)
            ->whereHas('permissions', function ($query) use ($permissionCodes) {
                $query->whereIn('permissions.code', $permissionCodes)
                    ->where('contract_permissions.is_active', true);
            })
            ->with('user:id,login')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->values()
            ->all();
    }

    private function titleForStatus(EventBookingStatus $status): string
    {
        return match ($status) {
            EventBookingStatus::Pending => 'Создана заявка на бронирование',
            EventBookingStatus::AwaitingPayment => 'Ожидается оплата бронирования',
            EventBookingStatus::Paid => 'Оплата бронирования получена',
            EventBookingStatus::Approved => 'Бронирование подтверждено',
            EventBookingStatus::Cancelled => 'Бронирование отменено',
        };
    }

    private function sendSystemMessage(EventBooking $booking, string $title, ?string $body, ?User $actor = null): int
    {
        $bookingCode = $this->resolveBookingCode($booking);
        $query = $bookingCode ? ('?booking=' . $bookingCode) : '';

        $creator = $booking->creator;
        $venueRecipients = $this->resolveVenueRecipients($booking);

        $recipientIds = collect([$creator?->id, ...array_map(static fn (User $user) => $user->id, $venueRecipients)])
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($recipientIds === []) {
            return 0;
        }

        $venueName = $booking->venue?->name;
        $label = 'Бронирование №' . ($bookingCode ?? $booking->id);
        if ($venueName) {
            $label .= ' — ' . $venueName;
        }
        $conversation = app(ConversationService::class)->findOrCreateSystem(
            EventBooking::class,
            $booking->id,
            $label,
            $recipientIds
        );

        $linkUrl = $booking->event?->id
            ? "/events/{$booking->event->id}{$query}"
            : ($booking->venue && $booking->venue->venueType
                ? "/venues/{$booking->venue->venueType->alias}/{$booking->venue->alias}/admin/bookings{$query}"
                : null);

        app(MessageService::class)->sendSystem($conversation, $title, $body, $linkUrl, $actor);

        return count($recipientIds);
    }
}
