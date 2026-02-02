<?php

namespace App\Domain\Users\Services;

use App\Domain\Users\Infrastructure\TelegramBotClient;
use Illuminate\Support\Str;

class TelegramWebhookService
{
    private const CONFIRM_PREFIX = 'confirm:';
    private const LOGIN_PREFIX = 'login_';

    public function __construct(
        private readonly ContactVerificationService $verificationService,
        private readonly TelegramLoginService $loginService,
        private readonly TelegramBotClient $botClient
    ) {
    }

    public function handle(array $payload): void
    {
        if (isset($payload['callback_query'])) {
            $this->handleCallback($payload['callback_query']);
        }

        if (isset($payload['message'])) {
            $this->handleMessage($payload['message']);
        }
    }

    private function handleMessage(array $message): void
    {
        $text = trim((string) ($message['text'] ?? ''));
        if ($text === '') {
            return;
        }

        if (Str::startsWith($text, '/account')) {
            $this->handleAccountCommand($message);
            return;
        }

        if (!Str::startsWith($text, '/start')) {
            return;
        }

        $token = trim((string) preg_replace('/^\/start\s*/u', '', $text));
        $chatId = $message['chat']['id'] ?? null;
        $username = $message['from']['username'] ?? null;

        if (!$chatId) {
            return;
        }

        if ($token === '') {
            $this->botClient->sendMessage($chatId, 'Нужна ссылка для подтверждения. Запросите её на сайте.');
            return;
        }

        if (Str::startsWith($token, self::LOGIN_PREFIX)) {
            $loginToken = substr($token, strlen(self::LOGIN_PREFIX));
            $result = $this->loginService->confirmLoginToken(
                $loginToken,
                is_string($username) ? $username : null,
                (string) $chatId
            );

            if (!$result['success']) {
                $this->botClient->sendMessage($chatId, $result['message']);
                return;
            }

            $replyMarkup = [];
            if (!empty($result['site_link'])) {
                $replyMarkup = [
                    'inline_keyboard' => [
                        [
                            [
                                'text' => 'Войти на сайт',
                                'url' => $result['site_link'],
                            ],
                        ],
                    ],
                ];
            }

            $this->botClient->sendMessage($chatId, $result['message'], $replyMarkup);
            return;
        }

        $verification = $this->verificationService->findPendingTelegramVerification($token);
        if (!$verification) {
            $this->botClient->sendMessage($chatId, 'Ссылка недействительна или истекла. Запросите новую на сайте.');
            return;
        }

        $replyMarkup = [
            'inline_keyboard' => [
                [
                    [
                        'text' => 'Подтвердить',
                        'callback_data' => self::CONFIRM_PREFIX . $token,
                    ],
                ],
            ],
        ];

        $this->botClient->sendMessage(
            $chatId,
            'Нажмите кнопку ниже, чтобы подтвердить контакт на сайте.',
            $replyMarkup
        );
    }

    private function handleAccountCommand(array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        $username = $message['from']['username'] ?? null;

        if (!$chatId) {
            return;
        }

        $this->loginService->handleAccountCommand(
            (string) $chatId,
            is_string($username) ? $username : null
        );
    }

    private function handleCallback(array $callback): void
    {
        $data = (string) ($callback['data'] ?? '');
        if (!Str::startsWith($data, self::CONFIRM_PREFIX)) {
            return;
        }

        $token = substr($data, strlen(self::CONFIRM_PREFIX));
        $callbackId = (string) ($callback['id'] ?? '');
        $chatId = $callback['message']['chat']['id'] ?? null;
        $username = $callback['from']['username'] ?? null;

        if ($token === '' || $callbackId === '') {
            return;
        }

        $message = $this->verificationService->confirmTelegramToken(
            $token,
            is_string($username) ? $username : null,
            $chatId !== null ? (string) $chatId : null
        );

        $this->botClient->answerCallbackQuery($callbackId, $message, false);

        if ($chatId) {
            $this->botClient->sendMessage($chatId, $message);
        }
    }
}
