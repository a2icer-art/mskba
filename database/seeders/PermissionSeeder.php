<?php

namespace Database\Seeders;

use App\Domain\Permissions\Models\Permission;
use App\Domain\Permissions\Registry\PermissionRegistry;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionRegistry::all() as $definition) {
            $code = $definition['code']->value;

            $scope = $definition['scope'] instanceof \BackedEnum
                ? $definition['scope']->value
                : $definition['scope'];

            Permission::query()->updateOrCreate(
                ['code' => $code],
                [
                    'label' => $definition['label'],
                    'scope' => $scope,
                    'target_model' => $definition['target_model'],
                ]
            );
        }
    }
}
