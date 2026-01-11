<?php

namespace App\Domain\Venues\Services;

use App\Domain\Moderation\Requirements\VenueModerationRequirements;
use App\Domain\Venues\Enums\VenueStatus;
use App\Domain\Venues\Models\Venue;

class VenueEditPolicy
{
    public function getEditableFields(Venue $venue): array
    {
        return VenueModerationRequirements::editableFields($this->isRestrictedStatus($venue));
    }

    public function isRestrictedStatus(Venue $venue): bool
    {
        return in_array($venue->status, [VenueStatus::Confirmed, VenueStatus::Moderation], true);
    }
}
