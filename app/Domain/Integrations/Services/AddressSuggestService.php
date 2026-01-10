<?php

namespace App\Domain\Integrations\Services;

use App\Domain\Integrations\Contracts\AddressSuggestProviderInterface;
use App\Domain\Integrations\Providers\Yandex\YandexAddressSuggestProvider;
use App\Domain\Cities\Models\City;
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
        $suggestions = $provider->suggest(trim($query));
        $defaultCountry = trim((string) config('integrations.address.default_country'));
        $cities = $this->getCityList();

        $result = [];
        foreach ($suggestions as $suggestion) {
            if (
                $defaultCountry !== ''
                && $suggestion->country
                && !$this->containsValue($suggestion->country, $defaultCountry)
            ) {
                continue;
            }

            $matchedCity = $this->matchCity($suggestion->city, $cities);
            if (!$matchedCity) {
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
                'country' => $suggestion->country,
                'city' => $matchedCity,
                'street' => $suggestion->street,
                'building' => $suggestion->building,
                'has_house' => (bool) $suggestion->building,
                'metro_id' => $metroId,
                'metro_name' => $metroName,
            ];
        }

        return $result;
    }

    private function resolveProvider(): AddressSuggestProviderInterface
    {
        $provider = config('integrations.address.provider', 'yandex');

        return match ($provider) {
            'yandex' => $this->yandexProvider,
            default => $this->yandexProvider,
        };
    }

    /**
     * @return string[]
     */
    private function getCityList(): array
    {
        return City::query()
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    private function matchCity(?string $city, array $cities): ?string
    {
        if (!$city) {
            return null;
        }

        $normalized = $this->normalizeValue($city);

        foreach ($cities as $name) {
            $normalizedName = $this->normalizeValue($name);
            if ($normalized === $normalizedName || $this->containsValue($normalized, $normalizedName)) {
                return $name;
            }
        }

        return null;
    }

    private function normalizeValue(string $value): string
    {
        return Str::lower(trim($value));
    }

    private function containsValue(string $haystack, string $needle): bool
    {
        return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
    }
}
