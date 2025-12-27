<?php

namespace App\Domain\Moderation\Enums;

enum ModerationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
