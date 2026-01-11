<?php

namespace App\Http\Controllers\Integrations;

use App\Domain\Integrations\Services\AddressSuggestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressSuggestController
{
    public function __invoke(Request $request, AddressSuggestService $service): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:3'],
        ]);

        try {
            $suggestions = $service->suggest($data['query']);
        } catch (\Throwable $exception) {
            report($exception);
            $suggestions = [];
        }

        return response()->json([
            'suggestions' => $suggestions,
        ]);
    }
}
