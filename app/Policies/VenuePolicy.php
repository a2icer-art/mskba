<?php

namespace App\Policies;

use App\Domain\Permissions\Enums\PermissionCode;
use App\Domain\Permissions\Services\PermissionChecker;
use App\Domain\Venues\Models\Venue;
use App\Models\User;

class VenuePolicy
{
    public function create(User $user): bool
    {
        return app(PermissionChecker::class)->can($user, PermissionCode::VenueCreate);
    }

    public function update(User $user, Venue $venue): bool
    {
        return app(PermissionChecker::class)->can($user, PermissionCode::VenueUpdate, $venue);
    }

    public function manageSchedule(User $user, Venue $venue): bool
    {
        return app(PermissionChecker::class)->can($user, PermissionCode::VenueScheduleManage, $venue);
    }

    public function activate(User $user, Venue $venue): bool
    {
        return app(PermissionChecker::class)->can($user, PermissionCode::VenueActivate, $venue);
    }

    public function deactivate(User $user, Venue $venue): bool
    {
        return app(PermissionChecker::class)->can($user, PermissionCode::VenueDeactivate, $venue);
    }

    public function block(User $user, Venue $venue): bool
    {
        return app(PermissionChecker::class)->can($user, PermissionCode::VenueBlock, $venue);
    }

    public function manageMedia(User $user, Venue $venue): bool
    {
        return app(PermissionChecker::class)->can($user, PermissionCode::VenueMediaManage, $venue);
    }
}
