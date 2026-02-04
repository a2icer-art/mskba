<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Enums\EventBookingPaymentConfirmStatus;
use App\Domain\Events\Enums\EventBookingPaymentConfirmationStatus;
use App\Domain\Events\Enums\EventBookingStatus;
use App\Domain\Events\Models\EventBooking;
use App\Domain\Events\Models\EventBookingPaymentConfirmation;
use App\Domain\Media\Services\MediaService;
use App\Domain\Payments\Enums\PaymentMethodType;
use App\Domain\Venues\Enums\VenueBookingMode;
use App\Domain\Venues\Models\VenueSettings;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventBookingPaymentConfirmationService
{
    public function request(
        EventBooking $booking,
        User $user,
        int $paymentMethodId,
        ?string $comment,
        ?UploadedFile $file
    ): EventBookingPaymentConfirmation {
        $this->ensureBookingCanRequest($booking);

        $methodSnapshot = $this->resolveMethodSnapshot($booking, $paymentMethodId);
        if (!$methodSnapshot) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'Метод оплаты не найден в бронировании.',
            ]);
        }

        $type = $methodSnapshot['type'] ?? null;
        if ($type !== PaymentMethodType::Sbp->value) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'Выбранный способ оплаты пока не поддерживается.',
            ]);
        }

        $comment = $comment !== null ? trim($comment) : null;
        if ($comment === '') {
            $comment = null;
        }

        if (!$comment && !$file) {
            throw ValidationException::withMessages([
                'evidence' => 'Добавьте скриншот или комментарий к оплате.',
            ]);
        }

        $confirmation = DB::transaction(function () use (
            $booking,
            $paymentMethodId,
            $methodSnapshot,
            $comment,
            $user,
            $file
        ): EventBookingPaymentConfirmation {
            $confirmation = EventBookingPaymentConfirmation::query()->create([
                'event_booking_id' => $booking->id,
                'payment_method_id' => $paymentMethodId,
                'payment_method_snapshot' => $methodSnapshot,
                'evidence_comment' => $comment,
                'status' => EventBookingPaymentConfirmationStatus::Pending,
                'requested_by_user_id' => $user->id,
            ]);

            if ($file) {
                $media = app(MediaService::class)->upload($file, $confirmation, 'payment_evidence', $user);
                $confirmation->update([
                    'evidence_media_id' => $media->id,
                ]);
            }

            $booking->update([
                'payment_confirm_status' => EventBookingPaymentConfirmStatus::UserPaidPending,
                'payment_confirmed_at' => null,
                'payment_last_confirmation_id' => $confirmation->id,
            ]);

            return $confirmation;
        });

        return $confirmation;
    }

    public function decide(
        EventBookingPaymentConfirmation $confirmation,
        User $user,
        bool $approved,
        ?string $comment
    ): EventBookingPaymentConfirmation {
        if ($confirmation->status !== EventBookingPaymentConfirmationStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Запрос уже обработан.',
            ]);
        }

        $booking = $confirmation->booking;
        if ($booking?->payment_due_at && $booking->payment_due_at->isPast()) {
            throw ValidationException::withMessages([
                'payment_due_at' => 'Срок оплаты истёк. Подтверждение недоступно.',
            ]);
        }

        $comment = $comment !== null ? trim($comment) : null;
        if ($comment === '') {
            $comment = null;
        }

        DB::transaction(function () use ($confirmation, $user, $approved, $comment, $booking): void {
            $confirmation->update([
                'status' => $approved
                    ? EventBookingPaymentConfirmationStatus::Approved
                    : EventBookingPaymentConfirmationStatus::Rejected,
                'decided_by_user_id' => $user->id,
                'decided_at' => now(),
                'decision_comment' => $comment,
            ]);

            if ($booking) {
                $updates = [
                    'payment_confirm_status' => $approved
                        ? EventBookingPaymentConfirmStatus::AdminConfirmed
                        : EventBookingPaymentConfirmStatus::UserPaidRejected,
                    'payment_confirmed_at' => $approved ? now() : null,
                    'payment_last_confirmation_id' => $confirmation->id,
                ];
                if ($approved && $booking->status === EventBookingStatus::AwaitingPayment) {
                    $updates['status'] = EventBookingStatus::Paid;
                    $mode = $booking->venue?->settings?->booking_mode ?? VenueBookingMode::Instant;
                    if ($mode === VenueBookingMode::Instant) {
                        $updates['status'] = EventBookingStatus::Approved;
                    }
                }
                $booking->update($updates);
            }
        });

        return $confirmation;
    }

    private function ensureBookingCanRequest(EventBooking $booking): void
    {
        if ($booking->status === EventBookingStatus::Cancelled) {
            throw ValidationException::withMessages([
                'booking' => 'Нельзя подтверждать оплату для отмененного бронирования.',
            ]);
        }

        $current = $booking->payment_confirm_status?->value ?? EventBookingPaymentConfirmStatus::None->value;
        if ($current === EventBookingPaymentConfirmStatus::UserPaidPending->value) {
            throw ValidationException::withMessages([
                'booking' => 'Запрос на подтверждение оплаты уже отправлен.',
            ]);
        }

        if ($current === EventBookingPaymentConfirmStatus::AdminConfirmed->value) {
            throw ValidationException::withMessages([
                'booking' => 'Оплата уже подтверждена администратором.',
            ]);
        }

        $venue = $booking->venue;
        if ($venue && $booking->starts_at) {
            $settings = $venue->settings()->first();
            $limitMinutes = (int) ($settings?->payment_confirmation_before_start_minutes
                ?? VenueSettings::DEFAULT_PAYMENT_CONFIRMATION_BEFORE_START_MINUTES);

            if ($limitMinutes > 0) {
                $cutoff = $booking->starts_at->copy()->subMinutes($limitMinutes);
                if (now()->greaterThanOrEqualTo($cutoff)) {
                    throw ValidationException::withMessages([
                        'booking' => 'Подтверждение оплаты недоступно менее чем за '
                            . $limitMinutes . ' минут до начала бронирования.',
                    ]);
                }
            }
        }
    }

    private function resolveMethodSnapshot(EventBooking $booking, int $paymentMethodId): ?array
    {
        $methods = is_array($booking->payment_methods_snapshot)
            ? $booking->payment_methods_snapshot
            : [];

        foreach ($methods as $method) {
            if ((int) ($method['id'] ?? 0) === $paymentMethodId) {
                if (array_key_exists('is_active', $method) && !$method['is_active']) {
                    return null;
                }
                return $method;
            }
        }

        return null;
    }
}
