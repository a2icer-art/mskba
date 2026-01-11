<?php

namespace App\Presentation\Venues;

use App\Domain\Metros\Models\Metro;
use App\Presentation\BasePresenter;

class MetroOptionsPresenter extends BasePresenter
{
    protected function buildData(array $ctx): array
    {
        return Metro::query()
            ->forOptions()
            ->get()
            ->map(fn (Metro $metro) => [
                'id' => $metro->id,
                'name' => $metro->name,
                'line_name' => $metro->line_name,
                'line_color' => $metro->line_color,
                'city' => $metro->city,
            ])
            ->values()
            ->all();
    }
}
