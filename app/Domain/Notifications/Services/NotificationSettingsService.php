<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Enums\NotificationCode;
use App\Domain\Notifications\Models\NotificationSetting;
use App\Domain\Users\Enums\ContactType;
use App\Models\User;

class NotificationSettingsService
{
    public function getDefinitions(): array
    {
        return [
            [
                'code' => NotificationCode::BookingStatus->value,
                'label' => 'Смена статуса бронирования',
            ],
            [
                'code' => NotificationCode::BookingPendingWarning->value,
                'label' => 'Предупреждение о скорой отмене бронирования',
            ],
            [
                'code' => NotificationCode::ContractModerationStatus->value,
                'label' => 'Смена статуса модерации контракта',
            ],
            [
                'code' => NotificationCode::ContractAssigned->value,
                'label' => 'Назначение контракта',
            ],
            [
                'code' => NotificationCode::ContractRevoked->value,
                'label' => 'Аннулирование контракта',
            ],
            [
                'code' => NotificationCode::ContractPermissionsUpdated->value,
                'label' => 'Изменение прав контракта',
            ],
        ];
    }

    public function getChannelOptions(): array
    {
        $labels = $this->getChannelLabels();
        $result = [];
        foreach (ContactType::cases() as $type) {
            $result[] = [
                'type' => $type->value,
                'label' => $labels[$type->value] ?? $type->value,
            ];
        }

        return $result;
    }

    public function getForUser(User $user): array
    {
        $settings = NotificationSetting::query()
            ->where('user_id', $user->id)
            ->first();
        $stored = is_array($settings?->settings) ? $settings->settings : [];

        return $this->mergeDefaults($stored);
    }

    public function updateForUser(User $user, array $settings): array
    {
        $normalized = $this->mergeDefaults($settings);

        NotificationSetting::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['settings' => $normalized]
        );

        return $normalized;
    }

    public function isEnabledForUser(User $user, string $code): bool
    {
        $settings = $this->getForUser($user);

        return (bool) ($settings[$code]['enabled'] ?? true);
    }

    public function getChannelsForUser(User $user, string $code): array
    {
        $settings = $this->getForUser($user);
        $channels = $settings[$code]['channels'] ?? [];
        $defaults = $this->getDefaultChannels();

        $normalized = [];
        foreach ($defaults as $type => $defaultValue) {
            $value = $channels[$type] ?? $defaultValue;
            $normalized[$type] = $this->normalizeChannelList($value);
        }

        return $normalized;
    }

    private function mergeDefaults(array $stored): array
    {
        $defaults = $this->getDefaults();
        $normalized = [];

        foreach ($defaults as $code => $default) {
            $source = $stored[$code] ?? [];
            $enabled = (bool) ($source['enabled'] ?? $default['enabled']);
            $channels = is_array($source['channels'] ?? null) ? $source['channels'] : [];
            $normalized[$code] = [
                'enabled' => $enabled,
                'channels' => $this->mergeChannels($channels),
            ];
        }

        return $normalized;
    }

    private function mergeChannels(array $channels): array
    {
        $defaults = $this->getDefaultChannels();
        $result = [];

        foreach ($defaults as $type => $defaultValue) {
            $value = $channels[$type] ?? $defaultValue;
            $result[$type] = $this->normalizeChannelList($value);
        }

        return $result;
    }

    private function getDefaults(): array
    {
        $defaults = [];
        $channels = $this->getDefaultChannels();

        foreach ($this->getDefinitions() as $definition) {
            $defaults[$definition['code']] = [
                'enabled' => true,
                'channels' => $channels,
            ];
        }

        return $defaults;
    }

    private function getDefaultChannels(): array
    {
        $channels = [];
        foreach (ContactType::cases() as $type) {
            $channels[$type->value] = [];
        }

        return $channels;
    }

    private function normalizeChannelList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(static fn ($item) => (int) $item)
            ->filter(static fn (int $item) => $item > 0)
            ->values()
            ->all();
    }

    private function getChannelLabels(): array
    {
        return [
            ContactType::Email->value => 'Email',
            ContactType::Phone->value => 'Телефон',
            ContactType::Telegram->value => 'Telegram',
            ContactType::Vk->value => 'VK',
            ContactType::Other->value => 'Другое',
        ];
    }
}
