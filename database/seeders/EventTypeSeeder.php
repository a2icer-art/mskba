<?php

namespace Database\Seeders;

use App\Domain\Events\Models\EventType;
use Illuminate\Database\Seeder;

class EventTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'game', 'label' => 'Игра'],
            ['code' => 'training', 'label' => 'Тренировка'],
            ['code' => 'game_training', 'label' => 'Игровая тренировка'],
        ];

        foreach ($types as $type) {
            EventType::query()->updateOrCreate(
                ['code' => $type['code']],
                ['label' => $type['label']]
            );
        }
    }
}
