<?php

namespace App\Domain\Users\Infrastructure;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotClient
{
    private string $token;

    public function __construct(?string $token = null)
    {
        $this->token = (string) ($token ?? config('services.telegram.bot_token'));
    }

    public function sendMessage(int|string $chatId, string $text, array $replyMarkup = []): void
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup !== []) {
            $payload['reply_markup'] = $replyMarkup;
        }

        $this->request('sendMessage', $payload);
    }

    public function answerCallbackQuery(string $callbackId, string $text, bool $showAlert = false): void
    {
        $this->request('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => $text,
            'show_alert' => $showAlert,
        ]);
    }

    private function request(string $method, array $payload): void
    {
        if ($this->token === '') {
            Log::warning('Telegram bot token is not configured.');
            return;
        }

        $url = sprintf('https://api.telegram.org/bot%s/%s', $this->token, $method);

        try {
            $response = Http::timeout(5)->post($url, $payload);

            if (!$response->successful()) {
                Log::warning('Telegram API request failed.', [
                    'method' => $method,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Telegram API request error.', [
                'method' => $method,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
