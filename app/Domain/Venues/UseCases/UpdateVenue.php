<?php

namespace App\Domain\Venues\UseCases;

use App\Domain\Venues\Models\Venue;
use App\Domain\Venues\Services\VenueEditPolicy;
use App\Domain\Moderation\Requirements\VenueModerationRequirements;
use App\Domain\Permissions\Enums\PermissionCode;
use App\Domain\Permissions\Services\PermissionChecker;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class UpdateVenue
{
    public function __construct(private readonly VenueEditPolicy $editPolicy)
    {
    }

    public function execute(User $actor, Venue $venue, array $data): Venue
    {
        $this->ensureOwner($actor, $venue);
        $this->ensureFieldsAllowed($venue, $data);
        $this->ensureHouseProvided($data);

        $allowed = $this->getAllowedFields($venue);

        $venueFields = Arr::only($data, array_intersect($allowed, [
            'name',
            'venue_type_id',
            'commentary',
            'str_address',
        ]));

        $addressFields = Arr::only($data, array_intersect($allowed, [
            'city',
            'metro_id',
            'street',
            'building',
        ]));

        if ($addressFields !== []) {
            $addressFields['str_address'] = $data['str_address'] ?? null;
            unset($venueFields['str_address']);
        }

        $venue->fill($venueFields);
        $venue->updated_by = $actor->id;
        $venue->save();

        if ($addressFields !== []) {
            $address = $venue->latestAddress;
            if (!$address) {
                $venue->addresses()->create(array_merge($addressFields, [
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]));
            } else {
                $address->fill($addressFields);
                $address->updated_by = $actor->id;
                $address->save();
            }
        }

        return $venue;
    }

    public function getValidationRules(Venue $venue): array
    {
        $rules = [
            'name' => ['nullable', 'string', 'max:255'],
            'venue_type_id' => ['nullable', 'integer', 'exists:venue_types,id'],
            'commentary' => ['nullable', 'string'],
            'str_address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'metro_id' => ['nullable', 'integer', 'exists:metros,id'],
            'street' => ['nullable', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:255'],
        ];

        if ($this->isRestrictedStatus($venue)) {
            $allowed = $this->getAllowedFields($venue);
            foreach (array_merge(
                VenueModerationRequirements::requiredVenueFields(),
                VenueModerationRequirements::requiredAddressFields()
            ) as $field) {
                if (!in_array($field, $allowed, true)) {
                    $rules[$field] = ['prohibited'];
                }
            }
        }

        return $rules;
    }

    public function getEditableFields(Venue $venue): array
    {
        return $this->editPolicy->getEditableFields($venue);
    }

    private function ensureOwner(User $actor, Venue $venue): void
    {
        if ($venue->created_by === $actor->id) {
            return;
        }

        $checker = app(PermissionChecker::class);
        if ($checker->can($actor, PermissionCode::VenueUpdate, $venue)) {
            return;
        }

        throw ValidationException::withMessages([
            'venue' => 'Редактировать площадку может только создатель.',
        ]);
    }

    private function ensureFieldsAllowed(Venue $venue, array $data): void
    {
        if (!$this->isRestrictedStatus($venue)) {
            return;
        }

        $required = array_merge(
            VenueModerationRequirements::requiredVenueFields(),
            VenueModerationRequirements::requiredAddressFields()
        );
        $restricted = array_diff($required, $this->getAllowedFields($venue));
        $attempted = array_intersect($restricted, array_keys($data));

        if ($attempted === []) {
            return;
        }

        $messages = [];
        foreach ($attempted as $field) {
            $messages[$field] = 'Поле недоступно для редактирования после подтверждения.';
        }

        throw ValidationException::withMessages($messages);
    }

    private function getAllowedFields(Venue $venue): array
    {
        return $this->getEditableFields($venue);
    }

    private function ensureHouseProvided(array $data): void
    {
        $addressFields = ['city', 'street', 'building', 'metro_id'];
        $hasAddress = array_intersect($addressFields, array_keys($data)) !== [];

        if (!$hasAddress) {
            return;
        }

        $city = $data['city'] ?? null;
        if (is_string($city)) {
            $city = trim($city);
        }

        $street = $data['street'] ?? null;
        if (is_string($street)) {
            $street = trim($street);
        }

        if (!$city || !$street) {
            throw ValidationException::withMessages([
                'city' => 'Необходимо указать адрес.',
            ]);
        }

        $building = $data['building'] ?? null;
        if (is_string($building)) {
            $building = trim($building);
        }

        if (!$building) {
            throw ValidationException::withMessages([
                'building' => 'Не указан дом.',
            ]);
        }
    }

    private function isRestrictedStatus(Venue $venue): bool
    {
        return $this->editPolicy->isRestrictedStatus($venue);
    }
}
