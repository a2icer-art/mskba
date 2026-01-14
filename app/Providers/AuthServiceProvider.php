<?php

namespace App\Providers;

use App\Domain\Permissions\Enums\PermissionCode;
use App\Domain\Permissions\Services\PermissionChecker;
use App\Domain\Venues\Models\Venue;
use App\Policies\VenuePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Venue::class => VenuePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        $checker = app(PermissionChecker::class);

        Gate::define(PermissionCode::AdminAccess->value, fn ($user) => $checker->can($user, PermissionCode::AdminAccess));
        Gate::define(PermissionCode::ModerationAccess->value, fn ($user) => $checker->can($user, PermissionCode::ModerationAccess));
        Gate::define(PermissionCode::LogsView->value, fn ($user) => $checker->can($user, PermissionCode::LogsView));
        Gate::define(PermissionCode::VenueCreate->value, fn ($user) => $checker->can($user, PermissionCode::VenueCreate));
        Gate::define(PermissionCode::VenueBooking->value, fn ($user) => $checker->can($user, PermissionCode::VenueBooking));
        Gate::define(PermissionCode::EventCreate->value, fn ($user) => $checker->can($user, PermissionCode::EventCreate));
        Gate::define(PermissionCode::CommentCreate->value, fn ($user) => $checker->can($user, PermissionCode::CommentCreate));
        Gate::define(PermissionCode::RatingCreate->value, fn ($user) => $checker->can($user, PermissionCode::RatingCreate));

        Gate::define(PermissionCode::ArticleCreate->value, fn ($user) => $checker->can($user, PermissionCode::ArticleCreate));
        Gate::define(PermissionCode::ArticleUpdate->value, fn ($user) => $checker->can($user, PermissionCode::ArticleUpdate));
        Gate::define(PermissionCode::ArticlePublish->value, fn ($user) => $checker->can($user, PermissionCode::ArticlePublish));
        Gate::define(PermissionCode::ArticleUnpublish->value, fn ($user) => $checker->can($user, PermissionCode::ArticleUnpublish));
        Gate::define(PermissionCode::ArticleDelete->value, fn ($user) => $checker->can($user, PermissionCode::ArticleDelete));

        Gate::define(PermissionCode::ArticleCategoryCreate->value, fn ($user) => $checker->can($user, PermissionCode::ArticleCategoryCreate));
        Gate::define(PermissionCode::ArticleCategoryUpdate->value, fn ($user) => $checker->can($user, PermissionCode::ArticleCategoryUpdate));
        Gate::define(PermissionCode::ArticleCategoryDelete->value, fn ($user) => $checker->can($user, PermissionCode::ArticleCategoryDelete));
    }
}
