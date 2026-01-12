<?php

namespace App\Domain\Permissions\Services;

use App\Domain\Permissions\Enums\PermissionCode;
use App\Domain\Permissions\Models\EntityPermission;
use App\Domain\Permissions\Models\Permission;
use App\Domain\Users\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PermissionChecker
{
    public function can(User $user, PermissionCode|string $code, ?Model $resource = null): bool
    {
        if ($user->status?->value === UserStatus::Blocked->value) {
            return false;
        }

        $codeValue = $code instanceof PermissionCode ? $code->value : $code;

        if ($this->hasUserPermission($user, $codeValue)) {
            return true;
        }

        if ($this->hasRolePermission($user, $codeValue)) {
            return true;
        }

        if ($resource && $this->hasEntityPermission($user, $codeValue, $resource)) {
            return true;
        }

        return false;
    }

    private function hasUserPermission(User $user, string $code): bool
    {
        return $user->permissions()
            ->where('code', $code)
            ->exists();
    }

    private function hasRolePermission(User $user, string $code): bool
    {
        return Permission::query()
            ->where('code', $code)
            ->whereHas('roles', function ($query) use ($user) {
                $query->whereHas('users', function ($subQuery) use ($user) {
                    $subQuery->where('users.id', $user->id);
                });
            })
            ->exists();
    }

    private function hasEntityPermission(User $user, string $code, Model $resource): bool
    {
        $permission = Permission::query()->where('code', $code)->first();
        if (!$permission) {
            return false;
        }

        return EntityPermission::query()
            ->where('permission_id', $permission->id)
            ->where('user_id', $user->id)
            ->where('entity_type', $resource->getMorphClass())
            ->where('entity_id', $resource->getKey())
            ->exists();
    }
}
