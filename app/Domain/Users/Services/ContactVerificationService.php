<?php

namespace App\Domain\Users\Services;

use App\Domain\Users\Infrastructure\ContactDeliveryResolver;
use App\Domain\Users\Enums\ContactType;
use App\Domain\Users\Models\ContactVerification;
use App\Domain\Users\Models\UserContact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContactVerificationService
{
    private const CODE_LENGTH = 6;
    private const CODE_TTL_MINUTES = 10;
    private const TELEGRAM_TOKEN_TTL_MINUTES = 15;
    public const MAX_ATTEMPTS = 5;

    public function requestCode(User $user, UserContact $contact): ContactVerification
    {
        if ($contact->type === ContactType::Telegram) {
            throw ValidationException::withMessages([
                'contact' => 'Подтверждение Telegram выполняется через бота.',
            ]);
        }

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

    public function requestTelegramLink(User $user, UserContact $contact): array
    {
        if ($contact->type !== ContactType::Telegram) {
            throw ValidationException::withMessages([
                'contact' => 'Контакт не относится к Telegram.',
            ]);
        }

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
                    'contact' => 'Ссылка уже выдана. Подождите.',
                    'wait_seconds' => (string) $waitSeconds,
                ]);
            }

            $verification->forceDelete();
            $verification = null;
        }

        $token = $this->generateToken();
        $tokenHash = $this->hashToken($token);
        $expiresAt = $now->copy()->addMinutes(self::TELEGRAM_TOKEN_TTL_MINUTES);

        $verification = $this->createTelegramVerification($user, $contact, $tokenHash, $expiresAt, $now);

        return [
            'verification' => $verification,
            'link' => $this->buildTelegramDeepLink($token),
            'expires_at' => $expiresAt,
        ];
    }

    public function verifyCode(User $user, UserContact $contact, string $code): void
    {
        if ($contact->type === ContactType::Telegram) {
            throw ValidationException::withMessages([
                'code' => 'Подтверждение Telegram выполняется через бота.',
            ]);
        }

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

    public function confirmTelegramToken(string $token, ?string $username = null, ?string $chatId = null): string
    {
        $verification = $this->findPendingTelegramVerification($token);
        if (!$verification) {
            return 'Ссылка недействительна или истекла.';
        }

        $contact = $verification->contact;
        if (!$contact || $contact->type !== ContactType::Telegram) {
            return 'Контакт не найден.';
        }

        if (!$this->matchesTelegramContact($contact->value, $username, $chatId)) {
            return 'Этот Telegram не соответствует указанному контакту.';
        }

        if ($contact->confirmed_at !== null) {
            return 'Контакт уже подтвержден.';
        }

        DB::transaction(function () use ($verification, $contact) {
            $verification->update([
                'verified_at' => now(),
                'updated_by' => $contact->user_id,
            ]);

            $contact->update([
                'confirmed_at' => now(),
                'updated_by' => $contact->user_id,
            ]);
        });

        return 'Контакт подтвержден.';
    }

    public function findPendingTelegramVerification(string $token): ?ContactVerification
    {
        $tokenHash = $this->hashToken($token);

        return ContactVerification::query()
            ->with('contact')
            ->where('token_hash', $tokenHash)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    private function generateCode(): string
    {
        $min = 10 ** (self::CODE_LENGTH - 1);
        $max = (10 ** self::CODE_LENGTH) - 1;

        return (string) random_int($min, $max);
    }

    private function generateToken(): string
    {
        $bytes = random_bytes(32);
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function matchesTelegramContact(string $value, ?string $username, ?string $chatId): bool
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return false;
        }

        if (preg_match('/^\d+$/', $normalized)) {
            return $chatId !== null && (string) $chatId === $normalized;
        }

        $normalized = ltrim($normalized, '@');
        $normalized = strtolower($normalized);
        $incoming = strtolower((string) ($username ?? ''));

        return $incoming !== '' && $incoming === $normalized;
    }

    private function buildTelegramDeepLink(string $token): string
    {
        $username = trim((string) config('services.telegram.bot_username'));
        if ($username === '') {
            throw ValidationException::withMessages([
                'contact' => 'Telegram-бот не настроен.',
            ]);
        }

        $username = ltrim($username, '@');

        return sprintf('https://t.me/%s?start=%s', $username, $token);
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

    private function createTelegramVerification(
        User $user,
        UserContact $contact,
        string $tokenHash,
        $expiresAt,
        $now
    ): ContactVerification {
        return DB::transaction(function () use ($user, $contact, $tokenHash, $expiresAt, $now) {
            ContactVerification::query()
                ->where('contact_id', $contact->id)
                ->forceDelete();

            return ContactVerification::query()->create([
                'user_id' => $user->id,
                'contact_id' => $contact->id,
                'code' => 'telegram',
                'token_hash' => $tokenHash,
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
