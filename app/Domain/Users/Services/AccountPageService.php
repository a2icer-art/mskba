<?php

namespace App\Domain\Users\Services;

use App\Domain\Moderation\Enums\ModerationEntityType;
use App\Domain\Moderation\Models\ModerationRequest;
use App\Domain\Participants\Enums\ParticipantRoleAssignmentStatus;
use App\Domain\Users\Enums\ContactType;
use App\Domain\Users\Models\ContactVerification;
use App\Domain\Users\Models\UserContact;
use App\Domain\Users\Resources\UserProfileViewResource;
use App\Models\User;
use App\Domain\Users\Services\ContactVerificationService;
use App\Domain\Balances\Services\BalanceService;
use App\Domain\Messages\Services\MessageCountersService;
use App\Presentation\Breadcrumbs\AccountBreadcrumbsPresenter;
use App\Presentation\Navigation\AccountNavigationPresenter;
use App\Support\DateFormatter;

class AccountPageService
{
    public function getProps(User $user, string $activeTab): array
    {
        $user->load(['profile', 'contacts', 'roles.permissions', 'permissions']);

        $participantRoles = $this->getParticipantRoles($user);
        $balance = app(BalanceService::class)->getOrCreate($user);
        $messageCounters = [
            'unread_messages' => app(MessageCountersService::class)->getUnreadMessages($user),
        ];
        $navigation = app(AccountNavigationPresenter::class)->present([
            'participantRoles' => $participantRoles,
            'messageCounters' => $messageCounters,
        ]);
        $breadcrumbs = app(AccountBreadcrumbsPresenter::class)->present([
            'activeTab' => $activeTab,
            'participantRoles' => $participantRoles,
        ])['data'];

        return [
            'activeTab' => $activeTab,
            'user' => [
                'id' => $user->id,
                'login' => $user->login,
                'status' => $user->status?->value,
                'created_at' => DateFormatter::dateTime($user->created_at),
                'confirmed_at' => DateFormatter::dateTime($user->confirmed_at),
                'block_reason' => $user->block_reason,
            ],
            'profile' => UserProfileViewResource::make($user->profile),
            'participantRoles' => $participantRoles,
            'emails' => $user->contacts
                ->where('type', ContactType::Email)
                ->sortBy('id')
                ->map(fn (UserContact $contact) => [
                    'id' => $contact->id,
                    'email' => $contact->value,
                    'confirmed_at' => DateFormatter::dateTime($contact->confirmed_at),
                ])
                ->values(),
            'contacts' => $user->contacts
                ->sortBy('id')
                ->map(fn (UserContact $contact) => [
                    'id' => $contact->id,
                    'type' => $contact->type?->value,
                    'value' => $contact->value,
                    'confirmed_at' => DateFormatter::dateTime($contact->confirmed_at),
                ])
                ->values(),
            'contactTypes' => [
                ['value' => ContactType::Email->value, 'label' => 'Электронная почта'],
                ['value' => ContactType::Phone->value, 'label' => 'Телефон'],
                ['value' => ContactType::Telegram->value, 'label' => 'Telegram'],
                ['value' => ContactType::Vk->value, 'label' => 'VK'],
                ['value' => ContactType::Other->value, 'label' => 'Другое'],
            ],
            'contactVerifications' => $this->getContactVerifications($user),
            'moderationRequest' => $this->getModerationRequest($user),
            'permissions' => $this->resolveUserPermissions($user),
            'balance' => [
                'available_amount' => $balance->available_amount,
                'held_amount' => $balance->held_amount,
                'currency' => $balance->currency?->value,
                'status' => $balance->status?->value,
                'block_reason' => $balance->block_reason,
                'blocked_at' => DateFormatter::dateTime($balance->blocked_at),
            ],
            'navigation' => $navigation,
            'breadcrumbs' => $breadcrumbs,
            'messageCounters' => $messageCounters,
        ];
    }

    public function getParticipantRoles(User $user): array
    {
        return $user->participantRoleAssignments()
            ->with('role:id,name,alias')
            ->where('status', ParticipantRoleAssignmentStatus::Confirmed)
            ->get()
            ->map(fn ($assignment) => [
                'id' => $assignment->id,
                'label' => $assignment->custom_title ?: ($assignment->role?->name ?? 'Роль'),
                'alias' => $assignment->role?->alias,
            ])
            ->values()
            ->all();
    }

    private function getContactVerifications(User $user): array
    {
        $contactVerifications = [];
        $contactIds = $user->contacts->pluck('id')->all();

        if ($contactIds === []) {
            return [];
        }

        $verifications = ContactVerification::query()
            ->whereIn('contact_id', $contactIds)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->get(['contact_id', 'attempts', 'expires_at']);

        foreach ($verifications as $verification) {
            if (array_key_exists($verification->contact_id, $contactVerifications)) {
                continue;
            }

            $contactVerifications[$verification->contact_id] = [
                'attempts' => $verification->attempts,
                'max_attempts' => ContactVerificationService::MAX_ATTEMPTS,
                'expires_at' => DateFormatter::dateTime($verification->expires_at),
            ];
        }

        return $contactVerifications;
    }

    private function getModerationRequest(User $user): ?array
    {
        $request = ModerationRequest::query()
            ->where('entity_type', ModerationEntityType::User->value)
            ->where('entity_id', $user->id)
            ->orderByDesc('submitted_at')
            ->first(['status', 'submitted_at', 'reviewed_at', 'reject_reason']);

        if (!$request) {
            return null;
        }

        return [
            'status' => $request->status?->value,
            'submitted_at' => DateFormatter::dateTime($request->submitted_at),
            'reviewed_at' => DateFormatter::dateTime($request->reviewed_at),
            'reject_reason' => $request->reject_reason,
        ];
    }

    private function resolveUserPermissions(User $user): array
    {
        $rolePermissions = $user->roles
            ? $user->roles->flatMap(fn ($role) => $role->permissions ?? collect())
            : collect();
        $userPermissions = $user->permissions ?? collect();

        return $rolePermissions
            ->merge($userPermissions)
            ->unique('code')
            ->sortBy('label')
            ->values()
            ->map(static function ($permission): array {
                return [
                    'code' => $permission->code,
                    'label' => $permission->label ?: $permission->code,
                ];
            })
            ->all();
    }
}
