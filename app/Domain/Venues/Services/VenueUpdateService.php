<?php

namespace App\Domain\Venues\Services;

use App\Domain\Moderation\Requirements\VenueModerationRequirements;
use App\Domain\Venues\Enums\VenueStatus;
use App\Domain\Venues\Models\Venue;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class VenueUpdateService
{
    public function updateVenue(User $actor, Venue $venue, array $data): Venue
    {
        $this->ensureOwner($actor, $venue);
        $this->ensureFieldsAllowed($venue, $data);

        $allowed = $this->getAllowedFields($venue);

        $venueFields = Arr::only($data, array_intersect($allowed, [
            'name',
            'venue_type_id',
            'commentary',
        ]));

        $addressFields = Arr::only($data, array_intersect($allowed, [
            'city',
            'metro_id',
            'street',
            'building',
            'str_address',
        ]));

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
            'city' => ['nullable', 'string', 'max:255'],
            'metro_id' => ['nullable', 'integer', 'min:1'],
            'street' => ['nullable', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:255'],
            'str_address' => ['nullable', 'string', 'max:255'],
        ];

        if ($this->isRestrictedStatus($venue)) {
            foreach (array_merge(
                VenueModerationRequirements::requiredVenueFields(),
                VenueModerationRequirements::requiredAddressFields()
            ) as $field) {
                $rules[$field] = ['prohibited'];
            }
        }

        return $rules;
    }

    public function getEditableFields(Venue $venue): array
    {
        return VenueModerationRequirements::editableFields($this->isRestrictedStatus($venue));
    }

    private function ensureOwner(User $actor, Venue $venue): void
    {
        if ($venue->created_by !== $actor->id) {
            throw ValidationException::withMessages([
                'venue' => 'Редактировать площадку может только создатель.',
            ]);
        }
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

    private function isRestrictedStatus(Venue $venue): bool
    {
        return in_array($venue->status, [VenueStatus::Confirmed, VenueStatus::Moderation], true);
    }
}
