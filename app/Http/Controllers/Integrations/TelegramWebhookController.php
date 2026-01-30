<?php

namespace App\Http\Controllers\Integrations;

use App\Domain\Users\Services\TelegramWebhookService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TelegramWebhookController
{
    public function __invoke(Request $request, TelegramWebhookService $service)
    {
        $secret = (string) config('services.telegram.webhook_secret');
        if ($secret !== '') {
            $header = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');
            if (!hash_equals($secret, $header)) {
                return response()->json(['ok' => false], Response::HTTP_FORBIDDEN);
            }
        }

        $service->handle($request->all());

        return response()->json(['ok' => true]);
    }
}
