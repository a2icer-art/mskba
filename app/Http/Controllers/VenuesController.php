<?php

namespace App\Http\Controllers;

use App\Domain\Moderation\Enums\ModerationEntityType;
use App\Domain\Moderation\Models\ModerationRequest;
use App\Domain\Moderation\UseCases\SubmitModerationRequest;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Venues\Enums\VenueStatus;
use App\Domain\Venues\Models\Venue;
use App\Domain\Venues\Services\VenueCatalogService;
use App\Support\DateFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class VenuesController extends Controller
{
    public function index()
    {
        $catalog = app(VenueCatalogService::class);
        $navItems = $catalog->getNavigationItems();
        $types = $catalog->getTypeOptions();
        $catalogData = $catalog->getHallsList();

        return Inertia::render('Halls', [
            'appName' => config('app.name'),
            'halls' => $catalogData['halls'],
            'activeType' => $catalogData['activeType'],
            'activeTypeSlug' => $catalogData['activeTypeSlug'],
            'navigation' => [
                'title' => 'Площадки',
                'items' => $navItems,
            ],
            'types' => $types,
        ]);
    }

    public function type(string $type)
    {
        $catalog = app(VenueCatalogService::class);
        $navItems = $catalog->getNavigationItems();
        $types = $catalog->getTypeOptions();
        $catalogData = $catalog->getHallsList($type);

        if (!$catalogData) {
            abort(404);
        }

        return Inertia::render('Halls', [
            'appName' => config('app.name'),
            'halls' => $catalogData['halls'],
            'activeType' => $catalogData['activeType'],
            'activeTypeSlug' => $catalogData['activeTypeSlug'],
            'navigation' => [
                'title' => 'Навигация',
                'items' => $navItems,
            ],
            'types' => $types,
        ]);
    }

    public function show(Venue $venue)
    {
        $venue->load(['venueType:id,name,alias', 'creator:id,login']);

        $catalog = app(VenueCatalogService::class);
        $navItems = $catalog->getNavigationItems();

        $latestRequest = ModerationRequest::query()
            ->where('entity_type', ModerationEntityType::Venue->value)
            ->where('entity_id', $venue->id)
            ->orderByDesc('submitted_at')
            ->first(['status', 'submitted_at', 'reviewed_at', 'reject_reason']);

        return Inertia::render('VenueShow', [
            'appName' => config('app.name'),
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'alias' => $venue->alias,
                'status' => $venue->status?->value,
                'address' => $venue->address,
                'created_at' => DateFormatter::dateTime($venue->created_at),
                'confirmed_at' => DateFormatter::dateTime($venue->confirmed_at),
                'type' => $venue->venueType?->only(['id', 'name', 'alias']),
                'creator' => $venue->creator?->only(['id', 'login']),
            ],
            'moderationRequest' => $latestRequest
                ? [
                    'status' => $latestRequest->status?->value,
                    'submitted_at' => DateFormatter::dateTime($latestRequest->submitted_at),
                    'reviewed_at' => DateFormatter::dateTime($latestRequest->reviewed_at),
                    'reject_reason' => $latestRequest->reject_reason,
                ]
                : null,
            'navigation' => [
                'title' => 'Площадки',
                'items' => $navItems,
            ],
            'activeTypeSlug' => $venue->venueType?->alias ? Str::plural($venue->venueType->alias) : '',
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->status !== UserStatus::Confirmed) {
            return back()->withErrors(['venue' => 'Добавление площадок доступно только подтвержденным пользователям.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'venue_type_id' => ['required', 'integer', 'exists:venue_types,id'],
        ]);

        $alias = $this->generateAlias($data['name']);

        $venue = Venue::query()->create([
            'name' => $data['name'],
            'alias' => $alias,
            'status' => VenueStatus::Unconfirmed,
            'created_by' => $user->id,
            'venue_type_id' => $data['venue_type_id'],
            'address' => $data['address'],
        ]);

        return redirect()->route('venues.show', $venue);
    }

    public function submitModerationRequest(Request $request, Venue $venue, SubmitModerationRequest $useCase)
    {
        $user = $request->user();
        if (!$user || $user->status !== UserStatus::Confirmed) {
            return back()->withErrors(['moderation' => 'Отправить на модерацию может только подтвержденный пользователь.']);
        }

        $result = $useCase->execute($user, ModerationEntityType::Venue, $venue);

        if (!$result->success) {
            return back()->withErrors([
                'moderation' => implode("\n", $result->missingRequirements ?? []),
            ]);
        }

        $venue->update([
            'status' => VenueStatus::Moderation,
        ]);

        return back();
    }

    private function generateAlias(string $name): string
    {
        $base = Str::slug($name);
        $alias = $base ?: 'venue';
        $counter = 1;

        while (Venue::query()->where('alias', $alias)->exists()) {
            $alias = $base ? $base . '-' . $counter : 'venue-' . $counter;
            $counter++;
        }

        return $alias;
    }
}
