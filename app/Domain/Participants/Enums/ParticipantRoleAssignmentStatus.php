<?php

namespace App\Domain\Participants\Enums;

enum ParticipantRoleAssignmentStatus: string
{
    case Unconfirmed = 'unconfirmed';
    case Moderation = 'moderation';
    case Confirmed = 'confirmed';
}
