<?php

namespace App\Domain\Contracts\UseCases;

use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Contracts\Enums\ContractType;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Moderation\DTO\SubmitModerationResult;
use App\Domain\Moderation\Enums\ModerationEntityType;
use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Models\ModerationRequest;
use App\Domain\Participants\Enums\ParticipantRoleAssignmentStatus;
use App\Domain\Participants\Models\ParticipantRoleAssignment;
use App\Domain\Venues\Enums\VenueStatus;
use App\Domain\Venues\Models\Venue;
use App\Domain\Users\Enums\UserStatus;
use App\Models\User;

class SubmitVenueContractModerationRequest
{
    public function execute(
        User $actor,
        Venue $venue,
        ContractType $type,
        ?string $comment = null
    ): SubmitModerationResult {
        if (!in_array($type, [ContractType::Owner, ContractType::Supervisor], true)) {
            return new SubmitModerationResult(false, null, ['Недоступный тип контракта для модерации.']);
        }

        if ($actor->status?->value !== UserStatus::Confirmed->value) {
            return new SubmitModerationResult(false, null, ['Требуется подтвержденный профиль пользователя.']);
        }

        if ($venue->status?->value !== VenueStatus::Confirmed->value) {
            return new SubmitModerationResult(false, null, ['Площадка должна быть подтверждена.']);
        }

        if ($type === ContractType::Owner && !$this->hasVenueAdminRole($actor)) {
            return new SubmitModerationResult(false, null, ['Нужна роль администратора площадки.']);
        }

        if ($type === ContractType::Supervisor && $this->hasActiveContract($actor, $venue)) {
            return new SubmitModerationResult(false, null, ['Нельзя стать супервайзером при наличии активного контракта.']);
        }

        if ($this->hasActiveContractType($venue, $type)) {
            return new SubmitModerationResult(false, null, ['Для площадки уже есть активный контракт этого типа.']);
        }

        $hasPending = ModerationRequest::query()
            ->where('entity_type', ModerationEntityType::VenueContract->value)
            ->where('entity_id', $venue->getKey())
            ->whereIn('status', [
                ModerationStatus::Pending->value,
                ModerationStatus::Clarification->value,
            ])
            ->where('meta->contract_type', $type->value)
            ->exists();

        if ($hasPending) {
            return new SubmitModerationResult(false, null, ['Заявка уже отправлена на модерацию.']);
        }

        $request = ModerationRequest::query()->create([
            'entity_type' => ModerationEntityType::VenueContract->value,
            'entity_id' => $venue->getKey(),
            'status' => ModerationStatus::Pending->value,
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
            'meta' => [
                'contract_type' => $type->value,
                'comment' => $comment,
            ],
        ]);

        return new SubmitModerationResult(true, $request);
    }

    private function hasVenueAdminRole(User $actor): bool
    {
        return ParticipantRoleAssignment::query()
            ->where('user_id', $actor->id)
            ->where('status', ParticipantRoleAssignmentStatus::Confirmed->value)
            ->whereHas('role', fn ($query) => $query->where('alias', 'venue-admin'))
            ->exists();
    }

    private function hasActiveContract(User $actor, Venue $venue): bool
    {
        $now = now();

        return Contract::query()
            ->where('user_id', $actor->id)
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

    private function hasActiveContractType(Venue $venue, ContractType $type): bool
    {
        $now = now();

        return Contract::query()
            ->where('entity_type', $venue->getMorphClass())
            ->where('entity_id', $venue->getKey())
            ->where('contract_type', $type->value)
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
}
