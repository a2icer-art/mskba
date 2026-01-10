<?php

namespace Database\Seeders;

use App\Domain\Cities\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            'Москва',
            'Химки',
            'Долгопрудный',
            'Балашиха',
            'Подольск',
            'Королёв',
            'Мытищи',
            'Люберцы',
            'Электросталь',
            'Одинцово',
            'Красногорск',
            'Домодедово',
            'Сергиев Посад',
        ];

        $now = now();
        $rows = [];

        foreach ($cities as $name) {
            $alias = Str::slug($name);
            if ($alias === '') {
                $alias = 'city-' . Str::uuid();
            }

            $rows[] = [
                'name' => $name,
                'alias' => $alias,
                'created_by' => null,
                'updated_by' => null,
                'deleted_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        City::query()->upsert(
            $rows,
            ['alias'],
            ['name', 'updated_at']
        );
    }
}
