<?php

namespace App\Domain\Contracts\Services;

use App\Domain\Contracts\DTO\ContractResult;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Contracts\Enums\ContractType;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Permissions\Enums\PermissionCode;
use App\Domain\Permissions\Enums\PermissionScope;
use App\Domain\Permissions\Models\Permission;
use App\Domain\Permissions\Services\PermissionChecker;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ContractManager
{
    public function assign(
        User $actor,
        User $target,
        Model $entity,
        ContractType $type,
        array $permissionCodes,
        ?string $name = null,
        ?\DateTimeInterface $startsAt = null,
        ?\DateTimeInterface $endsAt = null,
        ?string $comment = null
    ): ContractResult {
        if (!$this->canAssign($actor, $entity)) {
            return new ContractResult(false, null, 'Недостаточно прав для назначения контракта.');
        }

        if (in_array($type, [ContractType::Creator, ContractType::Owner, ContractType::Supervisor], true)) {
            return new ContractResult(false, null, 'Недоступный тип контракта для назначения.');
        }

        if (!$this->canAssignType($actor, $entity, $type)) {
            return new ContractResult(false, null, 'Недоступный тип контракта для назначения.');
        }

        if ($type === ContractType::Owner) {
            $typeConflict = Contract::query()
                ->where('entity_type', $entity->getMorphClass())
                ->where('entity_id', $entity->getKey())
                ->where('contract_type', $type->value)
                ->where('status', ContractStatus::Active->value)
                ->exists();

            if ($typeConflict) {
                return new ContractResult(false, null, 'Для этой сущности уже есть активный контракт такого типа.');
            }
        }

        $permissionCodes = $this->filterAssignablePermissionCodes($actor, $entity, $permissionCodes, $type);
        $permissionIds = $this->resolvePermissionIds($permissionCodes, $entity);

        $contract = Contract::query()->create([
            'user_id' => $target->id,
            'created_by' => $actor->id,
            'name' => $name ?: $type->label(),
            'contract_type' => $type,
            'entity_type' => $entity->getMorphClass(),
            'entity_id' => $entity->getKey(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => ContractStatus::Active,
            'comment' => $comment,
        ]);

        if ($permissionIds !== []) {
            $syncData = [];
            foreach ($permissionIds as $permissionId) {
                $syncData[$permissionId] = ['is_active' => true];
            }
            $contract->permissions()->sync($syncData);
        }

        return new ContractResult(true, $contract);
    }

    public function revoke(User $actor, Contract $contract, Model $entity): ContractResult
    {
        if (!$this->canRevoke($actor, $contract, $entity)) {
            return new ContractResult(false, null, 'Недостаточно прав для аннулирования контракта.');
        }

        if ($contract->contract_type?->value === ContractType::Creator->value) {
            return new ContractResult(false, null, 'Нельзя аннулировать контракт создателя.');
        }

        $contract->update([
            'status' => ContractStatus::Inactive,
            'ends_at' => $contract->ends_at ?: now(),
        ]);

        return new ContractResult(true, $contract);
    }

    public function updatePermissions(
        User $actor,
        Contract $contract,
        Model $entity,
        array $permissionCodes
    ): ContractResult {
        if (!$this->canUpdatePermissions($actor, $contract, $entity)) {
            return new ContractResult(false, null, 'Недостаточно прав для редактирования контракта.');
        }

        $targetType = $contract->contract_type;
        if (!$targetType) {
            return new ContractResult(false, null, 'Тип контракта не определен.');
        }

        $editableCodes = Permission::query()
            ->where('scope', PermissionScope::Resource)
            ->where('target_model', $entity::class)
            ->pluck('code')
            ->all();
        $editableCodes = $this->filterAssignablePermissionCodes($actor, $entity, $editableCodes, $targetType, false);
        $editableIds = $this->resolvePermissionIds($editableCodes, $entity);

        $requestedCodes = $this->filterAssignablePermissionCodes($actor, $entity, $permissionCodes, $targetType, false);
        $requestedIds = $this->resolvePermissionIds($requestedCodes, $entity);
        $requestedSet = array_flip($requestedIds);

        $currentIds = $contract->permissions()->pluck('permissions.id')->all();
        $currentSet = array_flip($currentIds);

        foreach ($editableIds as $permissionId) {
            $isActive = array_key_exists($permissionId, $requestedSet);

            if (array_key_exists($permissionId, $currentSet)) {
                $contract->permissions()->updateExistingPivot($permissionId, [
                    'is_active' => $isActive,
                ]);
            } else {
                $contract->permissions()->attach($permissionId, [
                    'is_active' => $isActive,
                ]);
            }
        }

        return new ContractResult(true, $contract);
    }

    public function canAssign(User $actor, Model $entity): bool
    {
        if (!app(PermissionChecker::class)->can($actor, PermissionCode::ContractAssign, $entity)) {
            return false;
        }

        if ($this->isAdmin($actor)) {
            return true;
        }

        $actorType = $this->resolveActorContractType($actor, $entity);

        return in_array($actorType, [ContractType::Creator, ContractType::Owner, ContractType::Supervisor], true);
    }

    public function canRevoke(User $actor, Contract $contract, Model $entity): bool
    {
        if (!app(PermissionChecker::class)->can($actor, PermissionCode::ContractRevoke, $entity)) {
            return false;
        }

        if ($this->isAdmin($actor)) {
            return true;
        }

        if ($contract->user_id === $actor->id) {
            return false;
        }

        if ($contract->created_by !== $actor->id) {
            return false;
        }

        $actorType = $this->resolveActorContractType($actor, $entity);
        $targetType = $contract->contract_type;

        if (!$actorType || !$targetType) {
            return false;
        }

        if (!in_array($actorType, [ContractType::Creator, ContractType::Owner, ContractType::Supervisor], true)) {
            return false;
        }

        return $actorType->level() > $targetType->level();
    }

    public function canUpdatePermissions(User $actor, Contract $contract, Model $entity): bool
    {
        if (!app(PermissionChecker::class)->can($actor, PermissionCode::ContractPermissionsUpdate, $entity)) {
            return false;
        }

        if ($this->isAdmin($actor)) {
            return true;
        }

        if ($contract->user_id === $actor->id) {
            return false;
        }

        $actorType = $this->resolveActorContractType($actor, $entity);
        $targetType = $contract->contract_type;

        if (!$actorType || !$targetType) {
            return false;
        }

        if (!in_array($actorType, [ContractType::Creator, ContractType::Owner, ContractType::Supervisor], true)) {
            return false;
        }

        if ($actorType === ContractType::Owner) {
            return $targetType !== ContractType::Supervisor;
        }

        if ($contract->created_by !== $actor->id) {
            return false;
        }

        return $actorType->level() > $targetType->level();
    }

    public function getAssignableTypes(User $actor, Model $entity): array
    {
        if (!$this->canAssign($actor, $entity)) {
            return [];
        }

        $types = array_filter(
            ContractType::cases(),
            static fn (ContractType $type) => !in_array(
                $type,
                [ContractType::Creator, ContractType::Owner, ContractType::Supervisor],
                true
            )
        );

        if ($this->isAdmin($actor)) {
            return array_values($types);
        }

        $actorType = $this->resolveActorContractType($actor, $entity);
        if (!$actorType) {
            return [];
        }

        return array_values(array_filter($types, static fn (ContractType $type) => $actorType->level() > $type->level()));
    }

    public function filterAssignablePermissionCodes(
        User $actor,
        Model $entity,
        array $permissionCodes,
        ContractType $targetType,
        bool $autoGrant = true
    ): array {
        $checker = app(PermissionChecker::class);
        $allowed = [];
        $isAdmin = $this->isAdmin($actor);

        foreach ($permissionCodes as $code) {
            if (!$isAdmin && !$checker->can($actor, $code, $entity)) {
                continue;
            }

            if ($code === PermissionCode::ContractAssign->value) {
                if ($this->canGrantContractAssign($actor, $entity, $targetType)) {
                    $allowed[] = $code;
                }
                continue;
            }

            if ($code === PermissionCode::ContractRevoke->value) {
                if ($this->canGrantContractRevoke($actor, $entity, $targetType)) {
                    $allowed[] = $code;
                }
                continue;
            }

            $allowed[] = $code;
        }

        if ($autoGrant && $this->shouldAutoGrantContractAssign($actor, $entity, $targetType)) {
            $allowed[] = PermissionCode::ContractAssign->value;
        }

        if ($autoGrant && $this->shouldAutoGrantContractRevoke($actor, $entity, $targetType)) {
            $allowed[] = PermissionCode::ContractRevoke->value;
        }

        return array_values(array_unique($allowed));
    }

    public function canGrantContractAssign(User $actor, Model $entity, ContractType $targetType): bool
    {
        if ($targetType !== ContractType::Employee) {
            return false;
        }

        if ($this->isAdmin($actor)) {
            return true;
        }

        $actorType = $this->resolveActorContractType($actor, $entity);

        return in_array($actorType, [ContractType::Creator, ContractType::Owner, ContractType::Supervisor], true);
    }

    public function canGrantContractRevoke(User $actor, Model $entity, ContractType $targetType): bool
    {
        if ($targetType !== ContractType::Employee) {
            return false;
        }

        if ($this->isAdmin($actor)) {
            return true;
        }

        $actorType = $this->resolveActorContractType($actor, $entity);

        return in_array($actorType, [ContractType::Creator, ContractType::Owner, ContractType::Supervisor], true);
    }

    private function shouldAutoGrantContractAssign(User $actor, Model $entity, ContractType $targetType): bool
    {
        return $this->canGrantContractAssign($actor, $entity, $targetType);
    }

    private function shouldAutoGrantContractRevoke(User $actor, Model $entity, ContractType $targetType): bool
    {
        return $this->canGrantContractRevoke($actor, $entity, $targetType);
    }

    private function resolvePermissionIds(array $permissionCodes, Model $entity): array
    {
        if ($permissionCodes === []) {
            return [];
        }

        return Permission::query()
            ->whereIn('code', $permissionCodes)
            ->where('scope', PermissionScope::Resource)
            ->where('target_model', $entity::class)
            ->pluck('id')
            ->all();
    }

    private function resolveActorContractType(User $actor, Model $entity): ?ContractType
    {
        if (property_exists($entity, 'created_by') && $entity->created_by === $actor->id) {
            return ContractType::Creator;
        }

        $now = now();
        $contracts = Contract::query()
            ->where('user_id', $actor->id)
            ->where('entity_type', $entity->getMorphClass())
            ->where('entity_id', $entity->getKey())
            ->where('status', ContractStatus::Active->value)
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->get();

        $best = null;
        foreach ($contracts as $contract) {
            $type = $contract->contract_type;
            if (!$type) {
                continue;
            }

            if (!$best || $type->level() > $best->level()) {
                $best = $type;
            }
        }

        return $best;
    }

    private function canAssignType(User $actor, Model $entity, ContractType $type): bool
    {
        if ($this->isAdmin($actor)) {
            return true;
        }

        $actorType = $this->resolveActorContractType($actor, $entity);
        if (!$actorType) {
            return false;
        }

        return $actorType->level() > $type->level();
    }

    private function isAdmin(User $actor): bool
    {
        return $actor->roles()
            ->where('alias', 'admin')
            ->exists();
    }
}
