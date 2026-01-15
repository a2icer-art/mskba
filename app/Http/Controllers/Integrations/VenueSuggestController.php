<?php

namespace App\Http\Controllers\Integrations;

use App\Domain\Venues\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VenueSuggestController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:2'],
        ]);

        $user = $request->user();
        $query = $data['query'];

        $venues = Venue::query()
            ->visibleFor($user)
            ->where(function ($builder) use ($query) {
                $builder->where('name', 'like', "%{$query}%")
                    ->orWhere('str_address', 'like', "%{$query}%")
                    ->orWhereHas('addresses', function ($addressQuery) use ($query) {
                        $addressQuery->where('str_address', 'like', "%{$query}%")
                            ->orWhere('city', 'like', "%{$query}%")
                            ->orWhere('street', 'like', "%{$query}%")
                            ->orWhere('building', 'like', "%{$query}%")
                            ->orWhereHas('metro', function ($metroQuery) use ($query) {
                                $metroQuery->where('name', 'like', "%{$query}%");
                            });
                    });
            })
            ->with(['latestAddress.metro'])
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'str_address']);

        $suggestions = $venues->map(function (Venue $venue): array {
            $address = $venue->latestAddress?->display_address ?: $venue->str_address;
            $metro = $venue->latestAddress?->metro?->name;

            $label = $venue->name;
            if ($address) {
                $label .= ' — ' . $address;
            }
            if ($metro) {
                $label .= ' (м. ' . $metro . ')';
            }

            return [
                'id' => $venue->id,
                'name' => $venue->name,
                'address' => $address,
                'metro' => $metro,
                'label' => $label,
            ];
        })->all();

        return response()->json([
            'suggestions' => $suggestions,
        ]);
    }
}
