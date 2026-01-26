<?php

namespace App\Domain\Users\Infrastructure;

use App\Domain\Users\Contracts\ContactDelivery;
use App\Domain\Users\Services\ContactDeliverySettingsService;
use App\Domain\Users\Models\UserContact;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailContactDelivery implements ContactDelivery
{
    public function send(UserContact $contact, string $code): bool
    {
        $settings = app(ContactDeliverySettingsService::class)->get();
        $emailSettings = $settings['email'] ?? [];

        if (!($emailSettings['enabled'] ?? false)) {
            return false;
        }

        $smtp = $emailSettings['smtp'] ?? [];
        $host = trim((string) ($smtp['host'] ?? ''));
        $port = (int) ($smtp['port'] ?? 0);
        $username = (string) ($smtp['username'] ?? '');
        $password = (string) ($smtp['password'] ?? '');
        $encryption = (string) ($smtp['encryption'] ?? 'tls');
        $scheme = null;
        $autoTls = true;
        if ($encryption === 'none') {
            $autoTls = false;
        } elseif ($encryption === 'ssl') {
            $scheme = 'smtps';
            $autoTls = false;
        }
        $fromAddress = trim((string) ($smtp['from_address'] ?? ''));
        $fromName = (string) ($smtp['from_name'] ?? config('app.name'));

        if ($host === '' || $port <= 0 || $fromAddress === '') {
            return false;
        }

        config([
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.username' => $username,
            'mail.mailers.smtp.password' => $password,
            'mail.mailers.smtp.encryption' => $encryption === 'none' ? null : $encryption,
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.mailers.smtp.auto_tls' => $autoTls,
            'mail.from.address' => $fromAddress,
            'mail.from.name' => $fromName,
        ]);

        try {
            $subject = 'Код подтверждения';
            $body = "Код подтверждения: {$code}";
            Mail::mailer('smtp')->raw($body, function ($message) use ($contact, $subject, $fromAddress, $fromName) {
                $message->to($contact->value)
                    ->subject($subject)
                    ->from($fromAddress, $fromName);
            });

            return true;
        } catch (\Throwable $exception) {
            Log::error('Email delivery failed.', [
                'contact_id' => $contact->id,
                'contact' => $contact->value,
                'error' => $exception->getMessage(),
            ]);
            return false;
        }
    }
}
