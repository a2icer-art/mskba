<?php

namespace App\Domain\Payments\Services;

use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Contracts\Enums\ContractType;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Payments\Models\PaymentMethod;
use App\Domain\Venues\Enums\VenuePaymentRecipientSource;
use App\Domain\Venues\Models\Venue;

class PaymentRecipientResolver
{
    public function resolveForVenue(Venue $venue): array
    {
        $settings = $venue->settings()->first();
        $source = $settings?->payment_recipient_source ?? VenuePaymentRecipientSource::Auto;

        if (is_string($source)) {
            $source = VenuePaymentRecipientSource::tryFrom($source) ?? VenuePaymentRecipientSource::Auto;
        }

        if ($source !== VenuePaymentRecipientSource::Auto) {
            $result = $this->resolveBySource($venue, $source);
            if ($result['methods'] !== []) {
                return $result;
            }
        }

        return $this->resolveAuto($venue);
    }

    private function resolveBySource(Venue $venue, VenuePaymentRecipientSource $source): array
    {
        if ($source === VenuePaymentRecipientSource::Supervisor) {
            return $this->resolveByContractType($venue, ContractType::Supervisor);
        }

        if ($source === VenuePaymentRecipientSource::Owner) {
            return $this->resolveByContractType($venue, ContractType::Owner);
        }

        if ($source === VenuePaymentRecipientSource::Venue) {
            return $this->resolveVenueMethods($venue);
        }

        return $this->resolveAuto($venue);
    }

    private function resolveAuto(Venue $venue): array
    {
        $supervisor = $this->resolveByContractType($venue, ContractType::Supervisor);
        if ($supervisor['methods'] !== []) {
            return $supervisor;
        }

        $owner = $this->resolveByContractType($venue, ContractType::Owner);
        if ($owner['methods'] !== []) {
            return $owner;
        }

        return $this->resolveVenueMethods($venue);
    }

    private function resolveByContractType(Venue $venue, ContractType $type): array
    {
        $contract = $this->getActiveContract($venue, $type);
        if (!$contract) {
            return $this->emptyResult();
        }

        $method = $this->resolveContractMethod($contract);
        if (!$method) {
            return $this->emptyResult();
        }

        return [
            'recipient_type' => Contract::class,
            'recipient_id' => $contract->id,
            'recipient_label' => $this->buildContractLabel($contract, $type),
            'methods' => [$this->formatMethod($method)],
        ];
    }

    private function resolveVenueMethods(Venue $venue): array
    {
        $methods = $this->getActiveMethods(Venue::class, $venue->id);

        return [
            'recipient_type' => Venue::class,
            'recipient_id' => $venue->id,
            'recipient_label' => $venue->name,
            'methods' => $methods,
        ];
    }

    private function buildContractLabel(Contract $contract, ContractType $type): string
    {
        $userLabel = $contract->user?->login;
        $typeLabel = $type->label();

        if ($userLabel) {
            return $typeLabel . ': ' . $userLabel;
        }

        return $contract->name ?: $typeLabel;
    }

    private function getActiveContract(Venue $venue, ContractType $type): ?Contract
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
            ->with(['user:id,login', 'paymentMethod'])
            ->orderByDesc('starts_at')
            ->first();
    }

    private function getActiveMethods(string $ownerType, int $ownerId): array
    {
        return PaymentMethod::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'type', 'label', 'phone', 'display_name', 'is_active', 'sort_order', 'is_default'])
            ->map(fn (PaymentMethod $method) => $this->formatMethod($method))
            ->all();
    }

    private function resolveContractMethod(Contract $contract): ?PaymentMethod
    {
        $user = $contract->user;
        $method = $contract->paymentMethod;

        if ($method && $method->is_active) {
            if ($user && $method->owner_type === $user->getMorphClass() && (int) $method->owner_id === (int) $user->id) {
                return $method;
            }
        }

        if (!$user) {
            return null;
        }

        return PaymentMethod::query()
            ->where('owner_type', $user->getMorphClass())
            ->where('owner_id', $user->id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    private function formatMethod(PaymentMethod $method): array
    {
        return [
            'id' => $method->id,
            'type' => $method->type?->value,
            'label' => $method->label,
            'phone' => $method->phone,
            'display_name' => $method->display_name,
            'is_active' => (bool) $method->is_active,
            'is_default' => (bool) $method->is_default,
            'sort_order' => (int) $method->sort_order,
        ];
    }

    private function emptyResult(): array
    {
        return [
            'recipient_type' => null,
            'recipient_id' => null,
            'recipient_label' => null,
            'methods' => [],
        ];
    }
}
