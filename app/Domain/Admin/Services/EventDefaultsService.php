<?php

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Models\AdminSetting;

class EventDefaultsService
{
    private const KEY = 'events.defaults';

    public function get(): array
    {
        $defaults = config('events');
        $settings = AdminSetting::query()->where('key', self::KEY)->first();
        if (!$settings) {
            return $defaults;
        }

        $value = is_array($settings->value) ? $settings->value : [];

        return [
            'lead_time_minutes' => (int) ($value['lead_time_minutes'] ?? $defaults['lead_time_minutes'] ?? 15),
            'min_duration_minutes' => (int) ($value['min_duration_minutes'] ?? $defaults['min_duration_minutes'] ?? 15),
        ];
    }

    public function update(array $data): void
    {
        AdminSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            ['value' => [
                'lead_time_minutes' => (int) $data['lead_time_minutes'],
                'min_duration_minutes' => (int) $data['min_duration_minutes'],
            ]]
        );
    }
}