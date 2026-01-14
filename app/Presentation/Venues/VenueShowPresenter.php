<?php

namespace App\Presentation\Venues;

use App\Domain\Moderation\Enums\ModerationEntityType;
use App\Domain\Moderation\Models\ModerationRequest;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Venues\Models\Venue;
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
        $editPolicy = app(VenueEditPolicy::class);
        $editableFields = $isOwner ? $editPolicy->getEditableFields($venue) : [];

        $latestRequest = ModerationRequest::query()
            ->where('entity_type', ModerationEntityType::Venue->value)
            ->where('entity_id', $venue->id)
            ->orderByDesc('submitted_at')
            ->first(['status', 'submitted_at', 'reviewed_at', 'reject_reason']);

        $address = $venue->latestAddress;

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
            'canEdit' => (bool) $isOwner,
            'canSubmitModeration' => $canSubmitModeration,
        ];
    }
}
