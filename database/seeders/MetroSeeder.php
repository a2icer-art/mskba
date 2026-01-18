<?php

namespace Database\Seeders;

use App\Domain\Metros\Models\Metro;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class MetroSeeder extends Seeder
{
    private const SOURCE_URL = 'https://api.hh.ru/metro/1';
    private const SOURCE_FILE = 'database/data/metros_moscow_2025.json';

    public function run(): void
    {
        $path = base_path(self::SOURCE_FILE);
        if (!file_exists($path)) {
            $this->command?->warn('MetroSeeder: source file not found: ' . $path . '. Скачать: ' . self::SOURCE_URL);
            return;
        }

        $payload = json_decode(file_get_contents($path), true);
        $lines = Arr::get($payload, 'lines', []);
        if ($lines === []) {
            $this->command?->warn('MetroSeeder: no lines found in source data.');
            return;
        }

        $now = now();
        $rows = [];
        $aliases = [];

        foreach ($lines as $line) {
            $lineName = (string) ($line['name'] ?? '');
            $lineColor = (string) ($line['hex_color'] ?? '');
            if ($lineColor !== '' && $lineColor[0] !== '#') {
                $lineColor = '#' . $lineColor;
            }
            $stations = $line['stations'] ?? [];

            foreach ($stations as $station) {
                $name = (string) ($station['name'] ?? '');
                if ($name === '') {
                    continue;
                }

                $alias = Str::slug(trim($name . ' ' . $lineName));
                if ($alias === '') {
                    $alias = 'metro-' . ($line['id'] ?? uniqid());
                }
                if (isset($aliases[$alias])) {
                    $alias .= '-' . ($line['id'] ?? uniqid());
                }
                $aliases[$alias] = true;

                $rows[] = [
                    'name' => $name,
                    'alias' => $alias,
                    'line_name' => $lineName,
                    'line_color' => $lineColor,
                    'city' => 'Москва',
                    'latitude' => isset($station['lat']) ? (float) $station['lat'] : null,
                    'longitude' => isset($station['lng']) ? (float) $station['lng'] : null,
                    'status' => 1,
                    'commentary' => null,
                    'created_by' => null,
                    'updated_by' => null,
                    'deleted_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows === []) {
            $this->command?->warn('MetroSeeder: no stations found in source data.');
            return;
        }

        Metro::query()->upsert(
            $rows,
            ['alias'],
            ['name', 'line_name', 'line_color', 'city', 'latitude', 'longitude', 'status', 'commentary', 'updated_at']
        );
    }
}
