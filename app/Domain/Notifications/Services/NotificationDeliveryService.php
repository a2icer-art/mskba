<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Users\Enums\ContactType;
use App\Domain\Users\Models\UserContact;
use App\Domain\Users\Services\ContactDeliverySettingsService;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationDeliveryService
{
    public function __construct(
        private readonly NotificationSettingsService $settingsService,
        private readonly ContactDeliverySettingsService $deliverySettingsService
    ) {
    }

    /**
     * @param User[] $recipients
     */
    public function sendExternal(
        string $notificationCode,
        array $recipients,
        string $title,
        ?string $body = null,
        ?string $linkUrl = null
    ): void {
        $recipients = collect($recipients)
            ->filter()
            ->unique('id')
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        $recipientIds = $recipients->pluck('id')->all();
        $contacts = UserContact::query()
            ->whereIn('user_id', $recipientIds)
            ->whereNotNull('confirmed_at')
            ->get(['id', 'user_id', 'type', 'value', 'confirmed_at'])
            ->groupBy('user_id');

        foreach ($recipients as $user) {
            if (!$this->settingsService->isEnabledForUser($user, $notificationCode)) {
                continue;
            }

            $channels = $this->settingsService->getChannelsForUser($user, $notificationCode);
            $allowedEmailIds = $channels[ContactType::Email->value] ?? [];
            if ($allowedEmailIds === []) {
                continue;
            }

            $userContacts = $contacts->get($user->id) ?? collect();
            $emailContacts = $userContacts
                ->filter(fn (UserContact $contact) => $contact->type === ContactType::Email)
                ->filter(fn (UserContact $contact) => in_array($contact->id, $allowedEmailIds, true));

            foreach ($emailContacts as $contact) {
                $this->sendEmail($contact, $title, $body, $linkUrl);
            }
        }
    }

    private function sendEmail(UserContact $contact, string $title, ?string $body, ?string $linkUrl): void
    {
        $settings = $this->deliverySettingsService->get();
        $emailSettings = $settings['email'] ?? [];

        if (!($emailSettings['enabled'] ?? false)) {
            return;
        }

        $smtp = $emailSettings['smtp'] ?? [];
        $host = trim((string) ($smtp['host'] ?? ''));
        $port = (int) ($smtp['port'] ?? 0);
        $username = (string) ($smtp['username'] ?? '');
        $password = (string) ($smtp['password'] ?? '');
        $encryption = (string) ($smtp['encryption'] ?? 'tls');
        $fromAddress = trim((string) ($smtp['from_address'] ?? ''));
        $fromName = (string) ($smtp['from_name'] ?? config('app.name'));

        if ($host === '' || $port <= 0 || $fromAddress === '') {
            return;
        }

        $encryption = $encryption === 'none' ? null : $encryption;

        config([
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.username' => $username,
            'mail.mailers.smtp.password' => $password,
            'mail.mailers.smtp.encryption' => $encryption,
            'mail.from.address' => $fromAddress,
            'mail.from.name' => $fromName,
        ]);

        $messageBody = $this->buildEmailBody($title, $body, $linkUrl);

        try {
            Mail::mailer('smtp')->raw($messageBody, function ($message) use ($contact, $title, $fromAddress, $fromName) {
                $message->to($contact->value)
                    ->subject($title)
                    ->from($fromAddress, $fromName);
            });
        } catch (\Throwable $exception) {
            Log::error('Notification email failed.', [
                'contact_id' => $contact->id,
                'contact' => $contact->value,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function buildEmailBody(string $title, ?string $body, ?string $linkUrl): string
    {
        $parts = [$title];
        if ($body) {
            $parts[] = $body;
        }
        if ($linkUrl) {
            $parts[] = 'Ссылка: ' . $linkUrl;
        }

        return implode("\n\n", $parts);
    }
}
