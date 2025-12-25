<?php

namespace App\Domain\Participants\Services;

use App\Domain\Participants\Models\CoachProfile;
use App\Domain\Participants\Models\MediaProfile;
use App\Domain\Participants\Models\OtherProfile;
use App\Domain\Participants\Models\PlayerProfile;
use App\Domain\Participants\Models\RefereeProfile;
use App\Domain\Participants\Models\SellerProfile;
use App\Domain\Participants\Models\StaffProfile;
use App\Domain\Participants\Models\VenueAdminProfile;

class ParticipantRoleProfileFactory
{
    private const ALIAS_MAP = [
        'player' => PlayerProfile::class,
        'coach' => CoachProfile::class,
        'referee' => RefereeProfile::class,
        'venue-admin' => VenueAdminProfile::class,
        'media' => MediaProfile::class,
        'seller' => SellerProfile::class,
        'staff' => StaffProfile::class,
        'other' => OtherProfile::class,
    ];

    public function createForAlias(string $alias, int $assignmentId, int $userId): void
    {
        $modelClass = self::ALIAS_MAP[$alias] ?? null;
        if ($modelClass === null) {
            return;
        }

        $modelClass::query()->create([
            'participant_role_assignment_id' => $assignmentId,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }
}
