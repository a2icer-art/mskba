<?php

namespace App\Domain\Venues\UseCases;

use App\Domain\Venues\Models\Amenity;
use App\Domain\Venues\Models\Venue;
use App\Domain\Venues\Models\VenueAmenity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateVenueAmenities
{
    public function execute(User $actor, Venue $venue, array $amenityIds, array $customNames = [], array $removeCustomIds = [], array $notes = []): void
    {
        DB::transaction(function () use ($actor, $venue, $amenityIds, $customNames, $removeCustomIds, $notes): void {
            $allowedAmenityIds = Amenity::query()
                ->whereIn('id', $amenityIds)
                ->where(function ($query) use ($venue): void {
                    $query
                        ->where('is_custom', false)
                        ->orWhere(function ($nested) use ($venue): void {
                            $nested->where('is_custom', true)
                                ->where('venue_id', $venue->id);
                        });
                })
                ->pluck('id')
                ->all();

            $createdAmenityIds = $this->createCustomAmenities($actor, $venue, $customNames);

            $removedCustomIds = Amenity::query()
                ->where('is_custom', true)
                ->where('venue_id', $venue->id)
                ->whereIn('id', $removeCustomIds)
                ->pluck('id')
                ->all();

            $attachIds = array_values(array_unique(array_merge($allowedAmenityIds, $createdAmenityIds)));
            if ($removedCustomIds !== []) {
                $attachIds = array_values(array_diff($attachIds, $removedCustomIds));
            }

            $this->detachAmenities($actor, $venue, $removedCustomIds);
            $this->syncAmenities($actor, $venue, $attachIds, $notes);
        });
    }

    private function createCustomAmenities(User $actor, Venue $venue, array $customNames): array
    {
        $createdAmenityIds = [];
        foreach ($customNames as $name) {
            if (!is_string($name)) {
                continue;
            }
            $trimmed = trim($name);
            if ($trimmed === '') {
                continue;
            }

            $existing = Amenity::query()
                ->withTrashed()
                ->where('is_custom', true)
                ->where('venue_id', $venue->id)
                ->where('name', $trimmed)
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->update([
                    'updated_by' => $actor->id,
                    'is_active' => true,
                ]);
                $createdAmenityIds[] = $existing->id;
                continue;
            }

            $alias = Str::slug($trimmed);
            $amenity = Amenity::query()->create([
                'name' => $trimmed,
                'alias' => $alias !== '' ? $alias : Str::random(8),
                'is_custom' => true,
                'venue_id' => $venue->id,
                'sort_order' => 0,
                'is_active' => true,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $createdAmenityIds[] = $amenity->id;
        }

        return $createdAmenityIds;
    }

    private function detachAmenities(User $actor, Venue $venue, array $removedAmenityIds): void
    {
        if ($removedAmenityIds === []) {
            return;
        }

        VenueAmenity::query()
            ->where('venue_id', $venue->id)
            ->whereIn('amenity_id', $removedAmenityIds)
            ->update([
                'deleted_by' => $actor->id,
            ]);

        VenueAmenity::query()
            ->where('venue_id', $venue->id)
            ->whereIn('amenity_id', $removedAmenityIds)
            ->delete();

        Amenity::query()
            ->where('is_custom', true)
            ->where('venue_id', $venue->id)
            ->whereIn('id', $removedAmenityIds)
            ->update([
                'deleted_by' => $actor->id,
            ]);

        Amenity::query()
            ->where('is_custom', true)
            ->where('venue_id', $venue->id)
            ->whereIn('id', $removedAmenityIds)
            ->delete();
    }

    private function syncAmenities(User $actor, Venue $venue, array $attachIds, array $notes): void
    {
        $currentIds = VenueAmenity::query()
            ->where('venue_id', $venue->id)
            ->whereNull('deleted_at')
            ->pluck('amenity_id')
            ->all();

        $toDetach = array_values(array_diff($currentIds, $attachIds));
        $toAttach = array_values(array_diff($attachIds, $currentIds));

        if ($toDetach !== []) {
            VenueAmenity::query()
                ->where('venue_id', $venue->id)
                ->whereIn('amenity_id', $toDetach)
                ->update([
                    'deleted_by' => $actor->id,
                ]);

            VenueAmenity::query()
                ->where('venue_id', $venue->id)
                ->whereIn('amenity_id', $toDetach)
                ->delete();
        }

        foreach ($toAttach as $amenityId) {
            $existing = VenueAmenity::query()
                ->withTrashed()
                ->where('venue_id', $venue->id)
                ->where('amenity_id', $amenityId)
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                $existing->update([
                    'updated_by' => $actor->id,
                    'deleted_by' => null,
                    'note' => $this->resolveNote($notes, $amenityId),
                ]);

                continue;
            }

            VenueAmenity::query()->create([
                'venue_id' => $venue->id,
                'amenity_id' => $amenityId,
                'note' => $this->resolveNote($notes, $amenityId),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        }

        $this->updateNotes($actor, $venue, $attachIds, $notes);
    }

    private function resolveNote(array $notes, int $amenityId): ?string
    {
        if (!array_key_exists($amenityId, $notes)) {
            return null;
        }

        $note = $notes[$amenityId];
        if (!is_string($note)) {
            return null;
        }

        $note = trim($note);

        return $note !== '' ? $note : null;
    }

    private function updateNotes(User $actor, Venue $venue, array $amenityIds, array $notes): void
    {
        if ($amenityIds === [] || $notes === []) {
            return;
        }

        foreach ($amenityIds as $amenityId) {
            if (!array_key_exists($amenityId, $notes)) {
                continue;
            }

            VenueAmenity::query()
                ->where('venue_id', $venue->id)
                ->where('amenity_id', $amenityId)
                ->update([
                    'note' => $this->resolveNote($notes, $amenityId),
                    'updated_by' => $actor->id,
                ]);
        }
    }
}
