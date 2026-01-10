<?php

namespace App\Domain\Integrations\Providers\Yandex;

use App\Domain\Integrations\Contracts\AddressSuggestProviderInterface;
use App\Domain\Integrations\DTO\AddressSuggestion;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class YandexAddressSuggestProvider implements AddressSuggestProviderInterface
{
    public function suggest(string $query): array
    {
        $apiKey = config('integrations.yandex.api_key');
        if (!$apiKey) {
            return [];
        }

        $suggestions = $this->fetchSuggestSuggestions($query);
        if (!count($suggestions)) {
            $suggestions = $this->fetchGeocodeSuggestions($query, null);
        }

        $houseNumber = $this->extractHouseNumber($query);
        $fallbackQuery = $this->stripHouseFromQuery($query);
        if ($houseNumber && $fallbackQuery && $fallbackQuery !== $query) {
            $fallback = $this->fetchGeocodeSuggestions($fallbackQuery, $houseNumber);
            $suggestions = $this->mergeSuggestions($suggestions, $fallback);
        }

        return array_slice($suggestions, 0, 5);
    }

    private function findComponent(array $components, array $kinds): ?string
    {
        foreach ($components as $component) {
            $kind = $component['kind'] ?? null;
            if ($kind && in_array($kind, $kinds, true)) {
                return $component['name'] ?? null;
            }
        }

        return null;
    }

    private function findComponents(array $components, array $kinds): array
    {
        $result = [];
        foreach ($components as $component) {
            $kind = $component['kind'] ?? null;
            if ($kind && in_array($kind, $kinds, true)) {
                $name = $component['name'] ?? null;
                if ($name) {
                    $result[] = $name;
                }
            }
        }

        return $result;
    }

    /**
     * @return AddressSuggestion[]
     */
    private function fetchGeocodeSuggestions(string $query, ?string $houseNumber): array
    {
        $response = Http::timeout(5)->get('https://geocode-maps.yandex.ru/1.x/', [
            'apikey' => config('integrations.yandex.api_key'),
            'geocode' => $query,
            'format' => 'json',
            'lang' => 'ru_RU',
            'results' => 10,
            'kind' => 'house',
        ]);

        if (!$response->ok()) {
            return [];
        }

        $members = Arr::get($response->json(), 'response.GeoObjectCollection.featureMember', []);
        if (!is_array($members)) {
            return [];
        }

        $suggestions = [];
        foreach ($members as $member) {
            $geo = $member['GeoObject'] ?? [];
            $meta = $geo['metaDataProperty']['GeocoderMetaData'] ?? [];
            $address = $meta['Address'] ?? [];
            $components = $address['Components'] ?? [];

            $label = (string) ($meta['text'] ?? '');
            $country = $this->findComponent($components, ['country']);
            $city = $this->findComponent($components, ['locality'])
                ?? $this->findComponent($components, ['province', 'area']);
            $street = $this->findComponent($components, ['street']);
            $building = $this->findComponent($components, ['house']);
            $metroNames = $this->findComponents($components, ['metro']);

            if (!$city || !$street || !$building) {
                continue;
            }

            if ($houseNumber && $building && !str_starts_with($building, $houseNumber)) {
                continue;
            }

            $suggestions[] = new AddressSuggestion(
                $label ?: trim($city . ', ' . $street . ', ' . $building),
                $country,
                $city,
                $street,
                $building,
                $metroNames
            );
        }

        return $suggestions;
    }

    private function extractHouseNumber(string $query): ?string
    {
        if (preg_match('/\b(\d+)\b/u', $query, $matches)) {
            return $matches[1] ?? null;
        }

        return null;
    }

    private function stripHouseFromQuery(string $query): string
    {
        return trim(preg_replace('/[,\s]+\d+\S*$/u', '', $query) ?? '');
    }

    /**
     * @param AddressSuggestion[] $base
     * @param AddressSuggestion[] $extra
     * @return AddressSuggestion[]
     */
    private function mergeSuggestions(array $base, array $extra): array
    {
        $seen = [];
        foreach ($base as $item) {
            $key = mb_strtolower($item->label);
            $seen[$key] = true;
        }

        foreach ($extra as $item) {
            $key = mb_strtolower($item->label);
            if (!isset($seen[$key])) {
                $base[] = $item;
                $seen[$key] = true;
            }
        }

        return $base;
    }

    /**
     * @return AddressSuggestion[]
     */
    private function fetchSuggestSuggestions(string $query): array
    {
        $response = Http::timeout(5)->get('https://suggest-maps.yandex.ru/v1/suggest', [
            'apikey' => config('integrations.yandex.api_key'),
            'text' => $query,
            'lang' => 'ru_RU',
            'types' => 'geo',
            'results' => 5,
        ]);

        if (!$response->ok()) {
            return [];
        }

        $results = Arr::get($response->json(), 'results', []);
        if (!is_array($results)) {
            return [];
        }

        $suggestions = [];
        foreach ($results as $item) {
            $text = $this->resolveSuggestText($item);
            if (!$text) {
                continue;
            }

            $geocoded = $this->fetchGeocodeSuggestions($text, null);
            $suggestions = $this->mergeSuggestions($suggestions, $geocoded);

            if (count($suggestions) >= 5) {
                break;
            }
        }

        return $suggestions;
    }

    private function resolveSuggestText(array $item): ?string
    {
        $text = Arr::get($item, 'text');
        if (is_string($text) && $text !== '') {
            return $text;
        }

        $title = Arr::get($item, 'title.text');
        $subtitle = Arr::get($item, 'subtitle.text');

        if ($title && $subtitle) {
            return trim($title . ', ' . $subtitle);
        }

        if ($title) {
            return (string) $title;
        }

        return null;
    }
}
