<?php

namespace App\Domain\Users\Services;

use App\Domain\Users\Infrastructure\TelegramBotClient;
use Illuminate\Support\Str;

class TelegramWebhookService
{
    private const CONFIRM_PREFIX = 'confirm:';

    public function __construct(
        private readonly ContactVerificationService $verificationService,
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
        if ($text === '' || !Str::startsWith($text, '/start')) {
            return;
        }

        $token = trim((string) preg_replace('/^\/start\s*/u', '', $text));
        $chatId = $message['chat']['id'] ?? null;

        if (!$chatId) {
            return;
        }

        if ($token === '') {
            $this->botClient->sendMessage($chatId, 'Нужна ссылка для подтверждения. Запросите её на сайте.');
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
