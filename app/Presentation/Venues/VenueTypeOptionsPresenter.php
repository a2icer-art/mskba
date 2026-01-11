<?php

namespace App\Presentation\Venues;

use App\Domain\Venues\Models\VenueType;
use App\Presentation\BasePresenter;

class VenueTypeOptionsPresenter extends BasePresenter
{
    protected function buildData(array $ctx): array
    {
        return VenueType::query()
            ->forOptions()
            ->get()
            ->map(fn (VenueType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'alias' => $type->alias,
            ])
            ->values()
            ->all();
    }
}
