<?php

namespace App\Domain\Venues\Builders;

use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Venues\Enums\VenueStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class VenueQueryBuilder extends Builder
{
    public function visibleFor(?User $user, ?int $roleLevel = null): self
    {
        if ($roleLevel === null) {
            $roleLevel = $user ? (int) $user->roles()->max('level') : 0;
        }

        if ($roleLevel > 20) {
            return $this;
        }

        $entityType = $this->getModel()->getMorphClass();
        $table = $this->getModel()->getTable();

        return $this->where(function (Builder $builder) use ($user, $entityType, $table): void {
            $builder->where('status', VenueStatus::Confirmed->value);

            if ($user) {
                $now = now();

                $builder->orWhere('created_by', $user->id);
                $builder->orWhereExists(function ($subQuery) use ($user, $entityType, $table, $now): void {
                    $subQuery->selectRaw('1')
                        ->from('contracts')
                        ->whereColumn('contracts.entity_id', "{$table}.id")
                        ->where('contracts.entity_type', $entityType)
                        ->where('contracts.user_id', $user->id)
                        ->where('contracts.status', ContractStatus::Active->value)
                        ->where(function ($dateQuery) use ($now): void {
                            $dateQuery->whereNull('contracts.starts_at')
                                ->orWhere('contracts.starts_at', '<=', $now);
                        })
                        ->where(function ($dateQuery) use ($now): void {
                            $dateQuery->whereNull('contracts.ends_at')
                                ->orWhere('contracts.ends_at', '>=', $now);
                        });
                });
            }
        });
    }
}
