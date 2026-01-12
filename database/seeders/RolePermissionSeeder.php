<?php

namespace Database\Seeders;

use App\Domain\Permissions\Models\Permission;
use App\Domain\Permissions\Registry\RolePermissionPreset;
use App\Domain\Users\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'moderator', 'editor'] as $roleAlias) {
            $role = Role::query()->where('alias', $roleAlias)->first();
            if (!$role) {
                continue;
            }

            $codes = array_map(
                static fn ($code) => $code->value,
                RolePermissionPreset::forRoleAlias($roleAlias)
            );

            if ($codes === []) {
                $role->permissions()->sync([]);
                continue;
            }

            $permissions = Permission::query()
                ->whereIn('code', $codes)
                ->pluck('id')
                ->all();

            $role->permissions()->sync($permissions);
        }
    }
}