<?php

namespace App\Domain\Users\Services;

use App\Domain\Users\Infrastructure\ContactDeliveryResolver;
use App\Domain\Users\Models\ContactVerification;
use App\Domain\Users\Models\UserContact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContactVerificationService
{
    private const CODE_LENGTH = 6;
    private const CODE_TTL_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;

    public function requestCode(User $user, UserContact $contact): ContactVerification
    {
        $verification = ContactVerification::query()
            ->where('contact_id', $contact->id)
            ->whereNull('verified_at')
            ->orderByDesc('id')
            ->first();

        $now = now();
        if ($verification) {
            if ($verification->expires_at > $now) {
                $waitSeconds = $now->diffInSeconds($verification->expires_at);
                throw ValidationException::withMessages([
                    'contact' => 'Код уже отправлен. Подождите.',
                    'wait_seconds' => (string) $waitSeconds,
                ]);
            }

            $verification->forceDelete();
            $verification = null;
        }

        $code = $this->generateCode();
        $expiresAt = now()->addMinutes(self::CODE_TTL_MINUTES);

        $delivery = app(ContactDeliveryResolver::class)->resolve($contact->type);
        $sent = $delivery->send($contact, $code);
        if (!$sent) {
            if (app()->environment('local')) {
                $this->createVerification($user, $contact, $code, $expiresAt, $now);
                throw ValidationException::withMessages([
                    'contact' => 'Не удалось отправить код. Обратитесь в техподдержку.',
                    'fallback' => '1',
                ]);
            }

            throw ValidationException::withMessages([
                'contact' => 'Не удалось отправить код. Обратитесь в техподдержку.',
            ]);
        }

        return $this->createVerification($user, $contact, $code, $expiresAt, $now);
    }

    public function verifyCode(User $user, UserContact $contact, string $code): void
    {
        $verification = ContactVerification::query()
            ->where('contact_id', $contact->id)
            ->whereNull('verified_at')
            ->orderByDesc('id')
            ->first();

        if (!$verification || $verification->expires_at <= now()) {
            throw ValidationException::withMessages([
                'code' => 'Код истек или не найден.',
            ]);
        }

        if ($verification->attempts >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages([
                'code' => 'Слишком много попыток. Запросите новый код.',
                'attempts_left' => '0',
                'max_attempts' => (string) self::MAX_ATTEMPTS,
            ]);
        }

        if ($verification->code !== $code) {
            $attempts = $verification->attempts + 1;
            $remainingAttempts = max(0, self::MAX_ATTEMPTS - $attempts);

            $verification->update([
                'attempts' => $attempts,
                'updated_by' => $user->id,
            ]);

            if ($remainingAttempts === 0) {
                throw ValidationException::withMessages([
                    'code' => 'Неверный код. Осталось попыток: 0.',
                    'attempts_left' => '0',
                    'max_attempts' => (string) self::MAX_ATTEMPTS,
                ]);
            }

            throw ValidationException::withMessages([
                'code' => 'Неверный код. Осталось попыток: ' . $remainingAttempts . '.',
                'attempts_left' => (string) $remainingAttempts,
                'max_attempts' => (string) self::MAX_ATTEMPTS,
            ]);
        }

        DB::transaction(function () use ($verification, $contact, $user) {
            $verification->update([
                'verified_at' => now(),
                'updated_by' => $user->id,
            ]);

            $contact->update([
                'confirmed_at' => now(),
                'updated_by' => $user->id,
            ]);
        });
    }

    private function generateCode(): string
    {
        $min = 10 ** (self::CODE_LENGTH - 1);
        $max = (10 ** self::CODE_LENGTH) - 1;

        return (string) random_int($min, $max);
    }

    private function createVerification(
        User $user,
        UserContact $contact,
        string $code,
        $expiresAt,
        $now
    ): ContactVerification {
        return DB::transaction(function () use ($user, $contact, $code, $expiresAt, $now) {
            ContactVerification::query()
                ->where('contact_id', $contact->id)
                ->forceDelete();

            return ContactVerification::query()->create([
                'user_id' => $user->id,
                'contact_id' => $contact->id,
                'code' => $code,
                'expires_at' => $expiresAt,
                'sent_at' => $now,
                'attempts' => 0,
                'verified_at' => null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });
    }
}
