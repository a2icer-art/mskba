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

        $venue->fill(Arr::only($data, $allowed));
        $venue->updated_by = $actor->id;
        $venue->save();

        return $venue;
    }

    public function getValidationRules(Venue $venue): array
    {
        $rules = [
            'name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'venue_type_id' => ['nullable', 'integer', 'exists:venue_types,id'],
            'commentary' => ['nullable', 'string'],
        ];

        if ($venue->status === VenueStatus::Confirmed) {
            foreach (VenueModerationRequirements::REQUIRED_FIELDS as $field) {
                $rules[$field] = ['prohibited'];
            }
        }

        return $rules;
    }

    public function getEditableFields(Venue $venue): array
    {
        return VenueModerationRequirements::editableFields($venue->status === VenueStatus::Confirmed);
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
        if ($venue->status !== VenueStatus::Confirmed) {
            return;
        }

        $restricted = array_diff(VenueModerationRequirements::REQUIRED_FIELDS, $this->getAllowedFields($venue));
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
}
