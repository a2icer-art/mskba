<?php

namespace App\Domain\Venues\Builders;

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

        return $this->where(function (Builder $builder) use ($user): void {
            $builder->where('status', VenueStatus::Confirmed->value);

            if ($user) {
                $builder->orWhere('created_by', $user->id);
            }
        });
    }
}
