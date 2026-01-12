<?php

namespace App\Http\Controllers;

use App\Domain\Moderation\Enums\ModerationEntityType;
use App\Domain\Moderation\UseCases\SubmitModerationRequest;
use App\Domain\Venues\Enums\VenueStatus;
use App\Domain\Venues\Models\Venue;
use App\Domain\Venues\Services\VenueCatalogService;
use App\Domain\Venues\UseCases\CreateVenue;
use App\Domain\Venues\UseCases\UpdateVenue;
use App\Http\Requests\Venues\StoreVenueRequest;
use App\Presentation\Navigation\VenueNavigationPresenter;
use App\Presentation\Venues\MetroOptionsPresenter;
use App\Presentation\Venues\VenueShowPresenter;
use App\Presentation\Venues\VenueTypeOptionsPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Inertia;

class VenuesController extends Controller
{
    public function index()
    {
        $user = request()->user();

        $navigation = app(VenueNavigationPresenter::class)->present([
            'title' => 'Площадки',
        ]);

        $types = app(VenueTypeOptionsPresenter::class)->present()['data'];
        $catalog = app(VenueCatalogService::class);
        $catalogData = $catalog->getHallsList(null, $user);

        return Inertia::render('Venues', [
            'appName' => config('app.name'),
            'venues' => $catalogData['venues'],
            'activeType' => $catalogData['activeType'],
            'activeTypeSlug' => $catalogData['activeTypeSlug'],
            'navigation' => $navigation,
            'types' => $types,
            'metros' => app(MetroOptionsPresenter::class)->present()['data'],
        ]);
    }

    public function type(string $type)
    {
        $user = request()->user();

        $navigation = app(VenueNavigationPresenter::class)->present([
            'title' => 'Площадки',
        ]);
        $catalog = app(VenueCatalogService::class);
        $types = app(VenueTypeOptionsPresenter::class)->present()['data'];
        $catalogData = $catalog->getHallsList($type, $user);

        if (!$catalogData) {
            abort(404);
        }

        return Inertia::render('Venues', [
            'appName' => config('app.name'),
            'venues' => $catalogData['venues'],
            'activeType' => $catalogData['activeType'],
            'activeTypeSlug' => $catalogData['activeTypeSlug'],
            'navigation' => $navigation,
            'types' => $types,
            'metros' => app(MetroOptionsPresenter::class)->present()['data'],
        ]);
    }

    public function show(string $type, Venue $venue)
    {
        $user = request()->user();
        $venue = Venue::query()
            ->visibleFor($user)
            ->whereKey($venue->id)
            ->firstOrFail();
        $venue->load(['venueType:id,name,alias', 'creator:id,login', 'latestAddress.metro:id,name,line_name,line_color,city']);

        $data = app(VenueShowPresenter::class)->present([
            'user' => $user,
            'venue' => $venue,
            'typeSlug' => $type,
        ])['data'];

        return Inertia::render('VenueShow', array_merge(
            ['appName' => config('app.name')],
            $data
        ));
    }

    public function store(StoreVenueRequest $request, CreateVenue $useCase)
    {
        $this->authorize('create', Venue::class);

        $user = $request->user();
        $data = $request->validated();
        $venue = $useCase->execute($user, $data);

        $typeSlug = $venue->venueType?->alias ? Str::plural($venue->venueType->alias) : '';

        return redirect()->route('venues.show', [
            'type' => $typeSlug,
            'venue' => $venue,
        ]);
    }

    public function update(Request $request, string $type, Venue $venue, UpdateVenue $useCase)
    {
        $this->authorize('update', $venue);

        $fields = $useCase->getEditableFields($venue);
        if ($fields === []) {
            return back()->withErrors(['venue' => 'Редактирование недоступно для этой площадки.']);
        }

        $rules = Arr::only($useCase->getValidationRules($venue), $fields);
        $data = validator($request->only($fields), $rules)->validate();
        $useCase->execute($request->user(), $venue, $data);

        return back();
    }

    public function submitModerationRequest(Request $request, string $type, Venue $venue, SubmitModerationRequest $useCase)
    {
        $result = $useCase->execute($request->user(), ModerationEntityType::Venue, $venue);

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

    // Методы генерации alias перенесены в доменный use case.
}
