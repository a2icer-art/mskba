<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Enums\EventParticipantRole;
use App\Domain\Events\Enums\EventParticipantStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventParticipant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class EventParticipantService
{
    public function invite(
        Event $event,
        User $inviter,
        User $participant,
        EventParticipantRole $role,
        ?string $reason = null
    ): EventParticipant
    {
        $this->ensureRoleAllowed($event, $role);

        return EventParticipant::updateOrCreate(
            [
                'event_id' => $event->id,
                'user_id' => $participant->id,
                'role' => $role->value,
            ],
            [
                'status' => EventParticipantStatus::Invited->value,
                'invited_by' => $inviter->id,
                'created_by' => $inviter->id,
                'joined_at' => null,
                'status_change_reason' => $reason,
                'status_changed_by' => $inviter->id,
                'status_changed_at' => Carbon::now(),
            ]
        );
    }

    public function join(
        Event $event,
        User $participant,
        EventParticipantRole $role,
        ?EventParticipantStatus $desiredStatus = null
    ): EventParticipant {
        $this->ensureRoleAllowed($event, $role);

        $status = $desiredStatus ?? EventParticipantStatus::Confirmed;
        if ($status === EventParticipantStatus::Confirmed && $this->isLimitRole($event, $role) && $this->isLimitReached($event)) {
            $status = EventParticipantStatus::Reserve;
        }

        return EventParticipant::updateOrCreate(
            [
                'event_id' => $event->id,
                'user_id' => $participant->id,
                'role' => $role->value,
            ],
            [
                'status' => $status->value,
                'invited_by' => null,
                'created_by' => $participant->id,
                'joined_at' => $status === EventParticipantStatus::Confirmed ? Carbon::now() : null,
                'status_change_reason' => null,
                'status_changed_by' => $participant->id,
                'status_changed_at' => Carbon::now(),
            ]
        );
    }

    public function respond(
        EventParticipant $participant,
        User $user,
        EventParticipantStatus $status,
        ?string $reason = null
    ): EventParticipant {
        if ($participant->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'participant' => 'Недостаточно прав для подтверждения участия.',
            ]);
        }

        $finalStatus = $status;
        if (
            $status === EventParticipantStatus::Confirmed
            && $this->isLimitRole($participant->event, $participant->role)
            && $this->isLimitReached($participant->event)
        ) {
            $finalStatus = EventParticipantStatus::Reserve;
        }

        $participant->status = $finalStatus;
        $participant->joined_at = $finalStatus === EventParticipantStatus::Confirmed ? Carbon::now() : null;
        $participant->user_status_reason = $reason;
        $participant->status_changed_by = $user->id;
        $participant->status_changed_at = Carbon::now();
        $participant->save();

        return $participant;
    }

    public function changeStatus(
        EventParticipant $participant,
        User $actor,
        EventParticipantStatus $status,
        ?string $reason = null
    ): EventParticipant {
        $finalStatus = $status;
        if (
            $status === EventParticipantStatus::Confirmed
            && $this->isLimitRole($participant->event, $participant->role)
            && $this->isLimitReached($participant->event)
        ) {
            $finalStatus = EventParticipantStatus::Reserve;
        }

        $participant->status = $finalStatus;
        $participant->joined_at = $finalStatus === EventParticipantStatus::Confirmed ? Carbon::now() : null;
        $participant->status_change_reason = $reason;
        $participant->status_changed_by = $actor->id;
        $participant->status_changed_at = Carbon::now();
        $participant->save();

        return $participant;
    }

    private function isLimitReached(Event $event): bool
    {
        $limit = (int) ($event->participants_limit ?? 0);
        if ($limit <= 0) {
            return false;
        }

        $limitRole = $this->getLimitRole($event);
        $confirmedCount = $event->participants
            ->where('status', EventParticipantStatus::Confirmed)
            ->where('role', $limitRole)
            ->count();

        return $confirmedCount >= $limit;
    }

    private function ensureRoleAllowed(Event $event, EventParticipantRole $role): void
    {
        $rules = $this->getEventRules($event);
        $allowed = $rules['allowed_roles'] ?? ['player'];
        if (!in_array($role->value, $allowed, true)) {
            throw ValidationException::withMessages([
                'role' => 'Роль недоступна для данного типа события.',
            ]);
        }
    }

    private function getLimitRole(Event $event): string
    {
        $rules = $this->getEventRules($event);
        return $rules['limit_role'] ?? 'player';
    }

    private function isLimitRole(Event $event, ?EventParticipantRole $role): bool
    {
        if ($role === null) {
            return false;
        }
        return $role->value === $this->getLimitRole($event);
    }

    private function getEventRules(Event $event): array
    {
        $event->loadMissing('type');
        $code = $event->type?->code;
        $rules = config('events.event_type_rules', []);

        $default = [
            'limit_role' => 'player',
            'allowed_roles' => ['player'],
        ];

        if ($code && isset($rules[$code])) {
            return array_merge($default, $rules[$code]);
        }

        return $default;
    }
}
