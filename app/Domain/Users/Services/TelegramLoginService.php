<?php

namespace App\Domain\Users\Services;

use App\Domain\Users\Enums\ContactType;
use App\Domain\Users\Enums\UserRegisteredVia;
use App\Domain\Users\Models\TelegramLoginToken;
use App\Domain\Users\Models\UserContact;
use App\Domain\Users\Infrastructure\TelegramBotClient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class TelegramLoginService
{
    private const TOKEN_TTL_MINUTES = 10;
    private const TOKEN_PREFIX = 'login_';
    private const PASSWORD_LENGTH = 8;

    public function __construct(
        private readonly RegisterUserService $registerUserService,
        private readonly TelegramBotClient $botClient
    ) {
    }

    public function createLoginToken(Request $request): array
    {
        $token = $this->generateToken();
        $tokenHash = $this->hashToken($token);
        $now = now();
        $expiresAt = $now->copy()->addMinutes(self::TOKEN_TTL_MINUTES);
        $link = $this->buildTelegramDeepLink($token);

        if ($link === '') {
            throw ValidationException::withMessages([
                'telegram' => 'Telegram-бот не настроен.',
            ]);
        }

        TelegramLoginToken::query()->create([
            'token_hash' => $tokenHash,
            'session_id' => $request->session()->getId(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'ip_address' => (string) $request->ip(),
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $token,
            'link' => $link,
            'expires_at' => $expiresAt,
        ];
    }

    public function confirmLoginToken(string $token, ?string $username, ?string $chatId): array
    {
        if ($chatId === null || $chatId === '') {
            return [
                'success' => false,
                'message' => 'Не удалось определить Telegram ID.',
            ];
        }

        $loginToken = $this->findPendingToken($token);
        if (!$loginToken) {
            return [
                'success' => false,
                'message' => 'Ссылка для входа недействительна или истекла.',
            ];
        }

        if ($loginToken->confirmed_at !== null) {
            if ($loginToken->telegram_id !== (string) $chatId) {
                return [
                    'success' => false,
                    'message' => 'Этот токен уже подтвержден другим Telegram.',
                ];
            }
        }

        $loginToken->update([
            'telegram_id' => (string) $chatId,
            'telegram_username' => $this->normalizeUsername($username),
            'confirmed_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'Подтверждено. Нажмите кнопку ниже, чтобы войти на сайт.',
            'site_link' => $this->buildSiteLoginLink($token),
        ];
    }

    public function completeLogin(string $token, Request $request): array
    {
        $loginToken = $this->findPendingToken($token);
        if (!$loginToken) {
            return [
                'success' => false,
                'message' => 'Ссылка для входа недействительна или истекла.',
            ];
        }

        if ($loginToken->confirmed_at === null || $loginToken->telegram_id === null) {
            return [
                'success' => false,
                'message' => 'Ссылка еще не подтверждена в Telegram.',
            ];
        }

        if ($loginToken->session_id && $loginToken->session_id !== $request->session()->getId()) {
            return [
                'success' => false,
                'message' => 'Сессия входа не совпадает. Начните вход заново.',
            ];
        }

        $user = $this->findUserByTelegram(
            $loginToken->telegram_id,
            $loginToken->telegram_username
        );

        $created = false;
        if (!$user) {
            $result = $this->createUserFromTelegram(
                $loginToken->telegram_id,
                $loginToken->telegram_username
            );
            $user = $result['user'];
            $created = $result['created'];
        }

        if (!$user) {
            return [
                'success' => false,
                'message' => 'Не удалось создать пользователя.',
            ];
        }

        $this->attachTelegramContact($user, $loginToken->telegram_id, $loginToken->telegram_username);

        $loginToken->update([
            'used_at' => now(),
            'user_id' => $user->id,
        ]);

        if ($created) {
            $this->sendCredentialsToTelegram($loginToken->telegram_id, $user);
        }

        return [
            'success' => true,
            'message' => 'Ок',
            'user' => $user,
        ];
    }

    public function handleAccountCommand(string $chatId, ?string $username = null): void
    {
        $user = $this->findUserByTelegram($chatId, $this->normalizeUsername($username));
        if (!$user) {
            $this->botClient->sendMessage($chatId, 'Аккаунт не найден. Начните вход на сайте через Telegram.');
            return;
        }

        $message = "Ваш аккаунт:\n";
        $message .= "Логин: {$user->login}\n";

        $password = $this->resolveStoredPassword($user);
        if ($password) {
            $message .= "Пароль (первоначальный): {$password}\n";
        } else {
            $message .= "Пароль: неизвестен (используйте восстановление на сайте).\n";
        }

        $this->botClient->sendMessage($chatId, $message);
    }

    public function buildTelegramStartToken(string $token): string
    {
        return self::TOKEN_PREFIX . $token;
    }

    private function findPendingToken(string $token): ?TelegramLoginToken
    {
        $tokenHash = $this->hashToken($token);

        return TelegramLoginToken::query()
            ->where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    private function createUserFromTelegram(string $telegramId, ?string $telegramUsername): array
    {
        $password = $this->generatePassword();
        $login = $this->generateLogin($telegramUsername, $telegramId);

        $user = $this->registerUserService->register([
            'login' => $login,
            'password' => $password,
            'registered_via' => UserRegisteredVia::TgLink,
            'registration_details' => [
                'telegram_auth' => [
                    'password' => Crypt::encryptString($password),
                    'password_sent_at' => now()->toIso8601String(),
                    'telegram_id' => $telegramId,
                    'telegram_username' => $telegramUsername,
                ],
            ],
        ]);

        return [
            'created' => true,
            'user' => $user,
        ];
    }

    private function generateLogin(?string $username, string $telegramId): string
    {
        $candidates = [];
        $normalized = $this->normalizeLogin($username);
        if ($normalized !== null && $normalized !== '') {
            $candidates[] = $normalized;
            $candidates[] = 'tg_' . $normalized;
        }
        $candidates[] = 'tg_' . $telegramId;

        foreach ($candidates as $candidate) {
            if (!$this->loginExists($candidate)) {
                return $candidate;
            }
        }

        return 'tg_' . $telegramId . '_' . Str::lower(Str::random(4));
    }

    private function loginExists(string $login): bool
    {
        return User::query()->where('login', $login)->exists();
    }

    private function normalizeLogin(?string $username): ?string
    {
        if (!$username) {
            return null;
        }

        $value = strtolower(ltrim($username, '@'));
        $value = preg_replace('/[^a-z0-9_]+/u', '', $value);
        $value = trim((string) $value, '_');

        return $value === '' ? null : $value;
    }

    private function normalizeUsername(?string $username): ?string
    {
        if (!$username) {
            return null;
        }

        $value = trim($username);
        if ($value === '') {
            return null;
        }

        return strtolower(ltrim($value, '@'));
    }

    private function generatePassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*';
        $max = strlen($alphabet) - 1;
        $password = '';

        for ($i = 0; $i < self::PASSWORD_LENGTH; $i++) {
            $password .= $alphabet[random_int(0, $max)];
        }

        return $password;
    }

    private function attachTelegramContact(User $user, string $telegramId, ?string $telegramUsername): void
    {
        $contactValue = $telegramId;

        $contact = UserContact::query()
            ->where('user_id', $user->id)
            ->where('type', ContactType::Telegram)
            ->where('value', $contactValue)
            ->first();

        if ($contact) {
            if ($contact->confirmed_at === null) {
                $contact->update([
                    'confirmed_at' => now(),
                    'updated_by' => $user->id,
                ]);
            }
        } else {
            UserContact::query()->create([
                'user_id' => $user->id,
                'type' => ContactType::Telegram,
                'value' => $contactValue,
                'confirmed_at' => now(),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        }

        if ($telegramUsername) {
            $normalized = '@' . $this->normalizeUsername($telegramUsername);
            $usernameContact = UserContact::query()
                ->where('user_id', $user->id)
                ->where('type', ContactType::Telegram)
                ->where('value', $normalized)
                ->first();

            if ($usernameContact) {
                if ($usernameContact->confirmed_at === null) {
                    $usernameContact->update([
                        'confirmed_at' => now(),
                        'updated_by' => $user->id,
                    ]);
                }
            } else {
                UserContact::query()->create([
                    'user_id' => $user->id,
                    'type' => ContactType::Telegram,
                    'value' => $normalized,
                    'confirmed_at' => now(),
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }
        }
    }

    private function findUserByTelegram(string $telegramId, ?string $telegramUsername): ?User
    {
        $values = [$telegramId];

        if ($telegramUsername) {
            $values[] = '@' . $this->normalizeUsername($telegramUsername);
        }

        $contact = UserContact::query()
            ->where('type', ContactType::Telegram)
            ->whereIn('value', $values)
            ->first();

        return $contact?->user;
    }

    private function sendCredentialsToTelegram(string $chatId, User $user): void
    {
        $password = $this->resolveStoredPassword($user);
        $message = "Создан аккаунт на сайте.\n";
        $message .= "Логин: {$user->login}\n";
        if ($password) {
            $message .= "Пароль: {$password}\n";
        }
        $message .= "Сохраните данные для входа.\n";

        $this->botClient->sendMessage($chatId, $message);
    }

    private function resolveStoredPassword(User $user): ?string
    {
        $details = $user->registration_details ?? [];
        $telegram = $details['telegram_auth'] ?? null;
        if (!is_array($telegram)) {
            return null;
        }

        $encrypted = $telegram['password'] ?? null;
        if (!$encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildTelegramDeepLink(string $token): string
    {
        $username = trim((string) config('services.telegram.bot_username'));
        $username = ltrim($username, '@');
        if ($username === '') {
            return '';
        }

        return sprintf('https://t.me/%s?start=%s', $username, $this->buildTelegramStartToken($token));
    }

    private function buildSiteLoginLink(string $token): string
    {
        return url('/auth/telegram/complete?token=' . $token);
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
}
