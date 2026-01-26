<?php

namespace App\Domain\Notifications\Enums;

enum NotificationCode: string
{
    case BookingStatus = 'booking.status';
    case BookingPendingWarning = 'booking.pending_warning';
    case ContractModerationStatus = 'contract.moderation_status';
    case ContractAssigned = 'contract.assigned';
    case ContractRevoked = 'contract.revoked';
    case ContractPermissionsUpdated = 'contract.permissions_updated';
    case MessageCreated = 'message.created';
}
