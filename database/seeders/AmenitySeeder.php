<?php

namespace Database\Seeders;

use App\Domain\Venues\Models\Amenity;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AmenitySeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::query()->where('login', 'admin')->value('id');

        $items = [
            'Парковка',
            'Душ',
            'Раздевалка',
            'Продуктовый магазин',
            'Туалет',
        ];

        foreach ($items as $index => $name) {
            $alias = Str::slug($name);
            Amenity::query()->updateOrCreate(
                ['alias' => $alias, 'is_custom' => false],
                [
                    'name' => $name,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'created_by' => $adminId,
                    'updated_by' => $adminId,
                ]
            );
        }
    }
}
