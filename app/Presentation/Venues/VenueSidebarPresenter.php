<?php

namespace App\Presentation\Venues;

use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Participants\Enums\ParticipantRoleAssignmentStatus;
use App\Domain\Participants\Models\ParticipantRoleAssignment;
use App\Domain\Permissions\Enums\PermissionCode;
use App\Domain\Permissions\Services\PermissionChecker;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Venues\Models\Venue;
use App\Models\User;
use App\Presentation\BasePresenter;

class VenueSidebarPresenter extends BasePresenter
{
    protected function resolveTitle(array $ctx): string
    {
        return $ctx['title'] ?? 'Площадки';
    }

    protected function buildData(array $ctx): array
    {
        $typeSlug = $ctx['typeSlug'] ?? '';
        /** @var Venue|null $venue */
        $venue = $ctx['venue'] ?? null;
        /** @var User|null $user */
        $user = $ctx['user'] ?? null;

        if (!$typeSlug || !$venue) {
            return [];
        }

        $groups = [
            [
                'title' => 'Общее',
                'items' => [
                    [
                        'label' => 'О площадке',
                        'href' => "/venues/{$typeSlug}/{$venue->alias}",
                    ],
                    [
                        'label' => 'Расписание',
                        'href' => "/venues/{$typeSlug}/{$venue->alias}/schedule",
                    ],
                    [
                        'label' => 'Лента',
                        'href' => "/venues/{$typeSlug}/{$venue->alias}/feed",
                    ],
                ],
            ],
        ];

        $canViewContracts = $this->canViewContracts($user, $venue);
        $canRequestContracts = $this->canRequestContracts($user, $venue);
        $isConfirmedUser = $user?->status?->value === UserStatus::Confirmed->value;
        $showContracts = $canViewContracts || $canRequestContracts || $isConfirmedUser;
        $canManageBookings = $this->canManageBookings($user, $venue);
        $canManageSettings = $this->canManageSettings($user, $venue);
        $canViewSupervisor = $this->canViewSupervisor($user, $venue);
        $canManageSchedule = $this->canManageSchedule($user, $venue);

        if ($showContracts || $canManageBookings || $canManageSettings || $canViewSupervisor || $canManageSchedule) {
            $adminItems = [];
            if ($showContracts) {
                $adminItems[] = [
                    'label' => 'Контракты',
                    'href' => "/venues/{$typeSlug}/{$venue->alias}/contracts",
                ];
            }
            if ($canManageSchedule) {
                $adminItems[] = [
                    'label' => 'Расписание',
                    'href' => "/venues/{$typeSlug}/{$venue->alias}/admin/schedule",
                ];
            }
            if ($canManageBookings) {
                $adminItems[] = [
                    'label' => 'Бронирование',
                    'href' => "/venues/{$typeSlug}/{$venue->alias}/bookings",
                ];
            }
            if ($canViewSupervisor) {
                $adminItems[] = [
                    'label' => 'Супервайзер',
                    'href' => "/venues/{$typeSlug}/{$venue->alias}/supervisor",
                ];
            }
            if ($canManageSettings) {
                $adminItems[] = [
                    'label' => 'Настройки',
                    'href' => "/venues/{$typeSlug}/{$venue->alias}/settings",
                ];
            }

            $groups[] = [
                'title' => 'Администрация',
                'items' => $adminItems,
            ];
        }

        return $groups;
    }

    private function canViewContracts(?User $user, Venue $venue): bool
    {
        if (!$user) {
            return false;
        }

        $isAdmin = $user->roles()
            ->where('alias', 'admin')
            ->exists();

        if ($isAdmin) {
            return true;
        }

        $now = now();

        return Contract::query()
            ->where('user_id', $user->id)
            ->where('entity_type', $venue->getMorphClass())
            ->where('entity_id', $venue->getKey())
            ->where('status', ContractStatus::Active->value)
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->exists();
    }

    private function canRequestContracts(?User $user, Venue $venue): bool
    {
        if (!$user) {
            return false;
        }

        return ParticipantRoleAssignment::query()
            ->where('user_id', $user->id)
            ->where('status', ParticipantRoleAssignmentStatus::Confirmed->value)
            ->whereHas('role', fn ($query) => $query->where('alias', 'venue-admin'))
            ->where(function ($query) use ($venue) {
                $query->whereNull('context_type')
                    ->whereNull('context_id')
                    ->orWhere(function ($subQuery) use ($venue) {
                        $subQuery->where('context_type', $venue->getMorphClass())
                            ->where('context_id', $venue->getKey());
                    });
            })
            ->exists();
    }

    private function canManageBookings(?User $user, Venue $venue): bool
    {
        if (!$user) {
            return false;
        }

        $checker = app(PermissionChecker::class);

        return $checker->can($user, PermissionCode::VenueBookingConfirm, $venue)
            || $checker->can($user, PermissionCode::VenueBookingCancel, $venue);
    }

    private function canManageSettings(?User $user, Venue $venue): bool
    {
        if (!$user) {
            return false;
        }

        $isAdmin = $user->roles()
            ->where('alias', 'admin')
            ->exists();

        if ($isAdmin) {
            return true;
        }

        $checker = app(PermissionChecker::class);

        return $checker->can($user, PermissionCode::VenueUpdate, $venue);
    }

    private function canViewSupervisor(?User $user, Venue $venue): bool
    {
        if (!$user) {
            return false;
        }

        $checker = app(PermissionChecker::class);

        return $checker->can($user, PermissionCode::VenueSupervisorView, $venue)
            || $checker->can($user, PermissionCode::VenueSupervisorManage, $venue);
    }

    private function canManageSchedule(?User $user, Venue $venue): bool
    {
        if (!$user) {
            return false;
        }

        $checker = app(PermissionChecker::class);

        return $checker->can($user, PermissionCode::VenueScheduleManage, $venue);
    }
}
