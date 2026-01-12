<?php

namespace App\Domain\Venues\UseCases;

use App\Domain\Venues\Enums\VenueStatus;
use App\Domain\Venues\Models\Venue;
use App\Domain\Permissions\Enums\PermissionCode;
use App\Domain\Permissions\Models\EntityPermission;
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
        $codes = [
            PermissionCode::VenueUpdate->value,
            PermissionCode::VenueScheduleManage->value,
            PermissionCode::VenueMediaManage->value,
        ];

        $permissionIds = Permission::query()
            ->whereIn('code', $codes)
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        $now = now();
        $rows = $permissionIds
            ->map(fn (int $permissionId) => [
                'permission_id' => $permissionId,
                'user_id' => $user->id,
                'entity_type' => $venue->getMorphClass(),
                'entity_id' => $venue->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        EntityPermission::query()->insertOrIgnore($rows);
    }
}
