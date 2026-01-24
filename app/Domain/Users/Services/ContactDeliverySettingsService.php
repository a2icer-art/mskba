<?php

namespace App\Domain\Users\Services;

use App\Domain\Admin\Models\AdminSetting;

class ContactDeliverySettingsService
{
    private const KEY = 'contact_delivery';

    public function get(): array
    {
        $settings = AdminSetting::query()->where('key', self::KEY)->first();
        $stored = is_array($settings?->value) ? $settings->value : [];

        return array_replace_recursive($this->defaults(), $stored);
    }

    public function update(array $data): array
    {
        $current = $this->get();
        $email = $current['email'] ?? [];
        $smtp = $email['smtp'] ?? [];

        $emailEnabled = (bool) ($data['email_enabled'] ?? false);
        $smtpPassword = $data['smtp_password'] ?? null;
        if ($smtpPassword === null || $smtpPassword === '') {
            $smtpPassword = $smtp['password'] ?? '';
        }

        $smtpPort = (int) ($data['smtp_port'] ?? 0);
        if ($smtpPort <= 0) {
            $smtpPort = (int) ($smtp['port'] ?? 587);
        }

        $smtpFromName = trim((string) ($data['smtp_from_name'] ?? ''));
        if ($smtpFromName === '') {
            $smtpFromName = (string) ($smtp['from_name'] ?? config('app.name'));
        }

        $updated = [
            'email' => [
                'enabled' => $emailEnabled,
                'smtp' => [
                    'host' => trim((string) ($data['smtp_host'] ?? '')),
                    'port' => $smtpPort,
                    'username' => trim((string) ($data['smtp_username'] ?? '')),
                    'password' => (string) $smtpPassword,
                    'encryption' => $data['smtp_encryption'] ?? 'tls',
                    'from_address' => trim((string) ($data['smtp_from_address'] ?? '')),
                    'from_name' => $smtpFromName,
                ],
            ],
            'phone' => [
                'enabled' => (bool) ($current['phone']['enabled'] ?? false),
            ],
            'telegram' => [
                'enabled' => (bool) ($current['telegram']['enabled'] ?? false),
            ],
            'vk' => [
                'enabled' => (bool) ($current['vk']['enabled'] ?? false),
            ],
            'other' => [
                'enabled' => (bool) ($current['other']['enabled'] ?? false),
            ],
        ];

        AdminSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            ['value' => $updated]
        );

        return $updated;
    }

    private function defaults(): array
    {
        return [
            'email' => [
                'enabled' => false,
                'smtp' => [
                    'host' => '',
                    'port' => 587,
                    'username' => '',
                    'password' => '',
                    'encryption' => 'tls',
                    'from_address' => '',
                    'from_name' => config('app.name'),
                ],
            ],
            'phone' => [
                'enabled' => false,
            ],
            'telegram' => [
                'enabled' => false,
            ],
            'vk' => [
                'enabled' => false,
            ],
            'other' => [
                'enabled' => false,
            ],
        ];
    }
}
