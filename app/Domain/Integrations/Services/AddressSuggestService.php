<?php

namespace App\Domain\Integrations\Services;

use App\Domain\Integrations\Contracts\AddressSuggestProviderInterface;
use App\Domain\Integrations\Providers\Yandex\YandexAddressSuggestProvider;
use App\Domain\Metros\Models\Metro;
use Illuminate\Support\Str;

class AddressSuggestService
{
    public function __construct(private readonly YandexAddressSuggestProvider $yandexProvider)
    {
    }

    public function suggest(string $query): array
    {
        $provider = $this->resolveProvider();
        $normalizedQuery = $this->normalizeQuery($query);
        $suggestions = $provider->suggest($normalizedQuery);
        $supportedCities = config('integrations.address.supported_cities', []);
        $defaultCountry = config('integrations.address.default_country');

        $result = [];
        foreach ($suggestions as $suggestion) {
            if ($defaultCountry && !$this->matchesValue($suggestion->label, $defaultCountry)) {
                continue;
            }
            $city = $this->matchSupportedCity($suggestion->city, $supportedCities)
                ?? $this->matchSupportedCity($normalizedQuery, $supportedCities);
            if (!$city) {
                continue;
            }

            $metroId = null;
            $metroName = null;

            foreach ($suggestion->metroNames as $name) {
                $metro = Metro::query()
                    ->whereRaw('lower(name) = ?', [Str::lower($name)])
                    ->first(['id', 'name']);
                if ($metro) {
                    $metroId = $metro->id;
                    $metroName = $metro->name;
                    break;
                }
            }

            $result[] = [
                'label' => $suggestion->label,
                'city' => $city,
                'street' => $suggestion->street,
                'building' => $suggestion->building,
                'has_house' => (bool) $suggestion->building,
                'metro_id' => $metroId,
                'metro_name' => $metroName,
            ];
        }

        return $result;
    }

    private function normalizeQuery(string $query): string
    {
        $normalized = trim($query);
        $defaultCountry = config('integrations.address.default_country');

        if (!$defaultCountry) {
            return $normalized;
        }

        if ($this->matchesValue($normalized, $defaultCountry)) {
            return $normalized;
        }

        return trim($defaultCountry . ' ' . $normalized);
    }

    private function resolveProvider(): AddressSuggestProviderInterface
    {
        $provider = config('integrations.address.provider', 'yandex');

        return match ($provider) {
            'yandex' => $this->yandexProvider,
            default => $this->yandexProvider,
        };
    }

    private function matchSupportedCity(?string $value, array $cities): ?string
    {
        if (!$value) {
            return null;
        }

        foreach ($cities as $city) {
            if (!$city) {
                continue;
            }
            if ($this->matchesValue($value, $city)) {
                return $city;
            }
        }

        return null;
    }

    private function matchesValue(string $haystack, string $needle): bool
    {
        return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
    }
}
