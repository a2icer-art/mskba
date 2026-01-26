<?php

namespace App\Domain\Contracts\Services;

use App\Domain\Contracts\Enums\ContractType;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Models\ModerationRequest;
use App\Domain\Messages\Services\ConversationService;
use App\Domain\Messages\Services\MessageService;
use App\Domain\Notifications\Enums\NotificationCode;
use App\Domain\Notifications\Services\NotificationDeliveryService;
use App\Domain\Notifications\Services\NotificationSettingsService;
use App\Domain\Venues\Models\Venue;
use App\Models\User;

class ContractNotificationService
{
    public function notifyModerationStatus(
        ModerationRequest $request,
        ModerationStatus $status,
        ?User $actor = null
    ): void {
        $notificationCode = NotificationCode::ContractModerationStatus->value;

        $request->loadMissing(['submitter:id,login', 'reviewer:id,login', 'entityVenue:id,name,alias,venue_type_id', 'entityVenue.venueType:id,alias']);
        $venue = $request->entityVenue;
        $submitter = $request->submitter;

        $recipientUsers = collect([$submitter, $actor])
            ->filter()
            ->unique('id')
            ->values();
        $settingsService = app(NotificationSettingsService::class);
        $recipientUsers = $recipientUsers
            ->filter(fn (User $user) => $settingsService->isEnabledForUser($user, $notificationCode))
            ->values();
        $recipientIds = $recipientUsers->pluck('id')->all();

        if ($recipientIds === []) {
            return;
        }

        $label = $venue ? "Контракт — {$venue->name}" : 'Контракт';
        $conversation = app(ConversationService::class)->findOrCreateSystem(
            ModerationRequest::class,
            $request->id,
            $label,
            $recipientIds
        );

        $statusLabel = match ($status) {
            ModerationStatus::Pending => 'На модерации',
            ModerationStatus::Approved => 'Подтверждено',
            ModerationStatus::Clarification => 'Требуются уточнения',
            ModerationStatus::Rejected => 'Отклонено',
        };
        $body = 'Статус: ' . $statusLabel;
        if (
            in_array($status, [ModerationStatus::Rejected, ModerationStatus::Clarification], true)
            && $request->reject_reason
        ) {
            $body .= "\nКомментарий: " . $request->reject_reason;
        }

        $linkUrl = $this->resolveVenueContractsUrl($venue);

        app(MessageService::class)->sendSystem(
            $conversation,
            'Изменение статуса модерации',
            $body,
            $linkUrl,
            $actor,
            $recipientIds
        );
        app(NotificationDeliveryService::class)->sendExternal(
            $notificationCode,
            $recipientUsers->all(),
            'Изменение статуса модерации',
            $body,
            $linkUrl
        );
    }

    public function notifyContractAssigned(Contract $contract, ?User $actor = null): void
    {
        $this->notifyContractEvent(
            $contract,
            'Назначение контракта',
            $this->buildContractBody($contract),
            $actor,
            NotificationCode::ContractAssigned->value
        );
    }

    public function notifyContractRevoked(Contract $contract, ?User $actor = null): void
    {
        $this->notifyContractEvent(
            $contract,
            'Аннулирование контракта',
            $this->buildContractBody($contract),
            $actor,
            NotificationCode::ContractRevoked->value
        );
    }

    public function notifyContractPermissionsUpdated(Contract $contract, ?User $actor = null): void
    {
        $this->notifyContractEvent(
            $contract,
            'Изменение прав контракта',
            $this->buildContractBody($contract),
            $actor,
            NotificationCode::ContractPermissionsUpdated->value
        );
    }

    private function notifyContractEvent(
        Contract $contract,
        string $title,
        ?string $body,
        ?User $actor,
        string $notificationCode
    ): void
    {
        $contract->loadMissing(['user:id,login', 'permissions:id,code,label', 'entity']);

        $recipientUsers = collect([$contract->user, $actor])
            ->filter()
            ->unique('id')
            ->values();
        $settingsService = app(NotificationSettingsService::class);
        $recipientUsers = $recipientUsers
            ->filter(fn (User $user) => $settingsService->isEnabledForUser($user, $notificationCode))
            ->values();
        $recipientIds = $recipientUsers->pluck('id')->all();

        if ($recipientIds === []) {
            return;
        }

        $venue = $this->resolveVenue($contract);
        $label = $venue ? "Контракт — {$venue->name}" : 'Контракт';

        $conversation = app(ConversationService::class)->findOrCreateSystem(
            Contract::class,
            $contract->id,
            $label,
            $recipientIds
        );

        $linkUrl = $this->resolveVenueContractsUrl($venue);

        app(MessageService::class)->sendSystem($conversation, $title, $body, $linkUrl, $actor, $recipientIds);
        app(NotificationDeliveryService::class)->sendExternal(
            $notificationCode,
            $recipientUsers->all(),
            $title,
            $body,
            $linkUrl
        );
    }

    private function buildContractBody(Contract $contract): string
    {
        $typeLabel = $contract->contract_type instanceof ContractType
            ? $contract->contract_type->label()
            : ($contract->contract_type?->value ?? 'Контракт');

        $body = 'Тип: ' . $typeLabel;

        $permissions = $contract->permissions
            ? $contract->permissions
                ->filter(static fn ($permission) => (bool) $permission->pivot?->is_active)
                ->pluck('label')
                ->filter()
                ->values()
                ->all()
            : [];

        if ($permissions !== []) {
            $body .= "\nПрава: " . implode(', ', $permissions);
        }

        if ($contract->comment) {
            $body .= "\nКомментарий: " . $contract->comment;
        }

        return $body;
    }

    private function resolveVenue(?Contract $contract): ?Venue
    {
        $entity = $contract?->entity;

        return $entity instanceof Venue ? $entity : null;
    }

    private function resolveVenueContractsUrl(?Venue $venue): ?string
    {
        if (!$venue || !$venue->venueType) {
            return null;
        }

        return "/venues/{$venue->venueType->alias}/{$venue->alias}/admin/contracts";
    }
}
