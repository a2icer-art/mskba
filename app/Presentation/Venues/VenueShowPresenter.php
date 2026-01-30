<?php

namespace App\Presentation\Venues;

use App\Domain\Moderation\Enums\ModerationEntityType;
use App\Domain\Moderation\Models\ModerationRequest;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Venues\Models\Venue;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Services\MediaService;
use App\Domain\Venues\Services\AmenityIconService;
use App\Domain\Venues\Services\VenueEditPolicy;
use App\Domain\Permissions\Enums\PermissionCode;
use App\Domain\Permissions\Services\PermissionChecker;
use App\Presentation\Breadcrumbs\VenueBreadcrumbsPresenter;
use App\Presentation\BasePresenter;
use App\Support\DateFormatter;

class VenueShowPresenter extends BasePresenter
{
    protected function buildData(array $ctx): array
    {
        /** @var Venue $venue */
        $venue = $ctx['venue'];
        $user = $ctx['user'] ?? null;
        $typeSlug = $ctx['typeSlug'] ?? '';

        $isOwner = $user && $venue->created_by === $user->id;
        $checker = app(PermissionChecker::class);
        $canSubmitModeration = $user
            && $user->status?->value === UserStatus::Confirmed->value
            && $checker->can($user, PermissionCode::VenueSubmitForModeration, $venue);
        $canEditByPermission = $user ? $checker->can($user, PermissionCode::VenueUpdate, $venue) : false;
        $editPolicy = app(VenueEditPolicy::class);
        $editableFields = ($isOwner || $canEditByPermission) ? $editPolicy->getEditableFields($venue) : [];

        $latestRequest = ModerationRequest::query()
            ->where('entity_type', ModerationEntityType::Venue->value)
            ->where('entity_id', $venue->id)
            ->orderByDesc('submitted_at')
            ->first(['status', 'submitted_at', 'reviewed_at', 'reject_reason']);

        $address = $venue->latestAddress;

        $mediaService = app(MediaService::class);
        $amenityIcons = app(AmenityIconService::class);
        $featuredMedia = $venue->media()
            ->where('is_featured', true)
            ->orderByDesc('id')
            ->get(['id', 'title', 'description', 'disk', 'path', 'is_featured'])
            ->map(fn ($media) => [
                'id' => $media->id,
                'title' => $media->title,
                'description' => $media->description,
                'url' => $media->path ? $mediaService->toPublicUrl($media) : null,
            ])
            ->values()
            ->all();

        $amenities = $venue->amenities
            ? $venue->amenities
                ->map(fn ($amenity) => [
                    'id' => $amenity->id,
                    'name' => $amenity->name,
                    'alias' => $amenity->alias,
                    'icon_url' => $amenityIcons->getUrl($amenity->icon_path),
                    'description' => $amenity->pivot?->note,
                ])
                ->values()
                ->all()
            : [];

        $totalMediaCount = Media::query()
            ->where('mediable_type', $venue->getMorphClass())
            ->where('mediable_id', $venue->id)
            ->count();

        $featuredMediaCount = Media::query()
            ->where('mediable_type', $venue->getMorphClass())
            ->where('mediable_id', $venue->id)
            ->where('is_featured', true)
            ->count();

        return [
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'alias' => $venue->alias,
                'status' => $venue->status?->value,
                'address' => $address
                    ? [
                        'city' => $address->city,
                        'metro_id' => $address->metro_id,
                        'street' => $address->street,
                        'building' => $address->building,
                        'str_address' => $address->str_address,
                        'display' => $address->display_address,
                        'metro' => $address->metro?->only(['id', 'name', 'line_name', 'line_color', 'city']),
                    ]
                    : null,
                'venue_type_id' => $venue->venue_type_id,
                'str_address' => $venue->str_address,
                'commentary' => $venue->commentary,
                'created_at' => DateFormatter::dateTime($venue->created_at),
                'confirmed_at' => DateFormatter::dateTime($venue->confirmed_at),
                'block_reason' => $venue->block_reason,
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
            'navigation' => app(VenueSidebarPresenter::class)->present([
                'title' => 'Площадки',
                'typeSlug' => $typeSlug,
                'venue' => $venue,
                'user' => $user,
            ]),
            'breadcrumbs' => app(VenueBreadcrumbsPresenter::class)->present([
                'venue' => $venue,
                'typeSlug' => $typeSlug,
            ])['data'],
            'activeTypeSlug' => $typeSlug,
            'types' => app(VenueTypeOptionsPresenter::class)->present()['data'],
            'editableFields' => $editableFields,
            'canEdit' => (bool) ($isOwner || $canEditByPermission),
            'canSubmitModeration' => $canSubmitModeration,
            'featuredMedia' => $featuredMedia,
            'featuredMediaCount' => $featuredMediaCount,
            'totalMediaCount' => $totalMediaCount,
            'amenities' => $amenities,
        ];
    }
}
