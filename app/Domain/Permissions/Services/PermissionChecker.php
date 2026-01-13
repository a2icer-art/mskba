<?php

namespace App\Domain\Permissions\Services;

use App\Domain\Permissions\Enums\PermissionCode;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Contracts\Models\Contract;
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

        if ($resource && $this->hasContractPermission($user, $codeValue, $resource)) {
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

    private function hasContractPermission(User $user, string $code, Model $resource): bool
    {
        $permission = Permission::query()->where('code', $code)->first();
        if (!$permission) {
            return false;
        }

        $entityType = $resource->getMorphClass();
        $entityId = $resource->getKey();
        $now = now();

        Contract::query()
            ->where('user_id', $user->id)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('status', ContractStatus::Active->value)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $now)
            ->update([
                'status' => ContractStatus::Inactive->value,
                'updated_at' => $now,
            ]);

        return Contract::query()
            ->where('user_id', $user->id)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('status', ContractStatus::Active->value)
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->whereHas('permissions', function ($query) use ($permission) {
                $query->where('permissions.id', $permission->id)
                    ->where('contract_permissions.is_active', true);
            })
            ->exists();
    }
}
