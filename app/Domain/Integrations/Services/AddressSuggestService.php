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

            [$metroId, $metroName] = $this->matchMetro(
                $suggestion->metroNames,
                $matchedCity,
                $suggestion->latitude,
                $suggestion->longitude
            );

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

    private function matchMetro(array $metroNames, ?string $city, ?float $latitude, ?float $longitude): array
    {
        $metros = $this->fetchMetroCandidates($city);
        if ($metros->isEmpty()) {
            return [null, null];
        }

        if ($metroNames !== []) {
            foreach ($metroNames as $name) {
                $needle = $this->normalizeMetroName($name);
                if ($needle === '') {
                    continue;
                }

                $exact = $metros->first(function (Metro $metro) use ($needle): bool {
                    return $this->normalizeMetroName($metro->name) === $needle;
                });
                if ($exact) {
                    return [$exact->id, $exact->name];
                }

                $partial = $metros->first(function (Metro $metro) use ($needle): bool {
                    $normalized = $this->normalizeMetroName($metro->name);
                    return $normalized !== '' && str_contains($normalized, $needle);
                });
                if ($partial) {
                    return [$partial->id, $partial->name];
                }
            }
        }

        if ($latitude === null || $longitude === null) {
            return [null, null];
        }

        $nearest = $this->resolveNearestMetro($metros, $latitude, $longitude);
        if (!$nearest) {
            return [null, null];
        }

        return [$nearest->id, $nearest->name];
    }

    private function fetchMetroCandidates(?string $city)
    {
        $metroQuery = Metro::query()->where('status', 1);
        if ($city) {
            $metroQuery->where('city', $city);
        }

        $metros = $metroQuery->get(['id', 'name', 'line_name', 'latitude', 'longitude']);
        if ($metros->isEmpty() && $city) {
            $metros = Metro::query()
                ->where('status', 1)
                ->get(['id', 'name', 'line_name', 'latitude', 'longitude']);
        }

        return $metros;
    }

    private function resolveNearestMetro($metros, float $latitude, float $longitude): ?Metro
    {
        $nearest = null;
        $nearestDistance = null;

        foreach ($metros as $metro) {
            if ($metro->latitude === null || $metro->longitude === null) {
                continue;
            }

            $distance = $this->distanceSquared($latitude, $longitude, $metro->latitude, $metro->longitude);
            if ($nearestDistance === null || $distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearest = $metro;
            }
        }

        return $nearest;
    }

    private function distanceSquared(float $latA, float $lonA, float $latB, float $lonB): float
    {
        $dLat = $latA - $latB;
        $dLon = $lonA - $lonB;

        return ($dLat * $dLat) + ($dLon * $dLon);
    }

    private function normalizeMetroName(?string $value): string
    {
        if (!$value) {
            return '';
        }

        $normalized = Str::lower(trim($value));
        $normalized = str_replace(['м.', 'м ', 'метро', 'станция', 'ст.'], '', $normalized);
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? '';
        $normalized = preg_replace('/\s+/u', ' ', trim($normalized)) ?? '';

        return $normalized;
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
