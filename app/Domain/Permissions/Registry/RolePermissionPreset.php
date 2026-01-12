<?php

namespace App\Domain\Permissions\Registry;

use App\Domain\Permissions\Enums\PermissionCode;

class RolePermissionPreset
{
    public static function forRoleAlias(string $alias): array
    {
        return match ($alias) {
            'admin' => PermissionRegistry::codes(),
            'moderator' => [
                PermissionCode::ModerationAccess,
                PermissionCode::VenueActivate,
                PermissionCode::VenueDeactivate,
                PermissionCode::VenueBlock,
            ],
            'editor' => [
                PermissionCode::CommentCreate,
                PermissionCode::RatingCreate,
                PermissionCode::ArticleCreate,
                PermissionCode::ArticleUpdate,
                PermissionCode::ArticlePublish,
                PermissionCode::ArticleUnpublish,
                PermissionCode::ArticleDelete,
                PermissionCode::ArticleCategoryCreate,
                PermissionCode::ArticleCategoryUpdate,
                PermissionCode::ArticleCategoryDelete,
            ],
            default => [],
        };
    }
}
