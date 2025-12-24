<?php

namespace App\Domain\Participants\Enums;

enum ParticipantRoleStatus: string
{
    case Unconfirmed = 'unconfirmed';
    case Moderation = 'moderation';
    case Confirmed = 'confirmed';
}
