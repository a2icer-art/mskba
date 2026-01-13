<?php

namespace App\Domain\Venues\UseCases;

use App\Domain\Venues\Enums\VenueStatus;
use App\Domain\Venues\Models\Venue;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Contracts\Enums\ContractType;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Permissions\Enums\PermissionScope;
use App\Domain\Permissions\Models\Permission;
use App\Models\User;
use App\Support\AliasGenerator;

class CreateVenue
{
    public function __construct(private readonly AliasGenerator $aliasGenerator)
    {
    }

    public function execute(User $user, array $data): Venue
    {
        $venue = Venue::query()->create([
            'name' => $data['name'],
            'alias' => $this->aliasGenerator->generateUnique($data['name'], Venue::class, 'alias', 'venue'),
            'status' => VenueStatus::Unconfirmed,
            'created_by' => $user->id,
            'venue_type_id' => $data['venue_type_id'],
        ]);

        $venue->addresses()->create([
            'city' => $data['city'],
            'metro_id' => $data['metro_id'] ?? null,
            'street' => $data['street'],
            'building' => $data['building'],
            'str_address' => $data['str_address'] ?? null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->grantOwnerPermissions($user, $venue);

        return $venue;
    }

    private function grantOwnerPermissions(User $user, Venue $venue): void
    {
        $permissionIds = Permission::query()
            ->where('scope', PermissionScope::Resource)
            ->where('target_model', Venue::class)
            ->pluck('id')
            ->all();

        if ($permissionIds === []) {
            return;
        }

        $contract = Contract::query()
            ->where('user_id', $user->id)
            ->where('entity_type', $venue->getMorphClass())
            ->where('entity_id', $venue->getKey())
            ->where('status', ContractStatus::Active->value)
            ->first();

        if (!$contract) {
            $contract = Contract::query()->create([
                'user_id' => $user->id,
                'created_by' => $user->id,
                'name' => 'Создатель',
                'contract_type' => ContractType::Creator,
                'entity_type' => $venue->getMorphClass(),
                'entity_id' => $venue->getKey(),
                'starts_at' => now(),
                'ends_at' => null,
                'status' => ContractStatus::Active,
                'comment' => null,
            ]);
        }

        $syncData = [];
        foreach ($permissionIds as $permissionId) {
            $syncData[$permissionId] = ['is_active' => true];
        }
        $contract->permissions()->syncWithoutDetaching($syncData);
    }
}
