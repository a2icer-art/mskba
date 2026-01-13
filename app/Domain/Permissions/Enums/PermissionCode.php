<?php

namespace App\Domain\Permissions\Enums;

enum PermissionCode: string
{
    case AdminAccess = 'admin.access';
    case ModerationAccess = 'moderation.access';
    case LogsView = 'logs.view';

    case VenueCreate = 'venue.create';
    case EventCreate = 'event.create';
    case CommentCreate = 'comment.create';
    case RatingCreate = 'rating.create';

    case VenueUpdate = 'venue.update';
    case VenueSubmitForModeration = 'venue.submit_for_moderation';
    case VenueScheduleManage = 'venue.schedule.manage';
    case VenueActivate = 'venue.activate';
    case VenueDeactivate = 'venue.deactivate';
    case VenueBlock = 'venue.block';
    case VenueMediaManage = 'venue.media.manage';

    case ArticleCreate = 'article.create';
    case ArticleUpdate = 'article.update';
    case ArticlePublish = 'article.publish';
    case ArticleUnpublish = 'article.unpublish';
    case ArticleDelete = 'article.delete';

    case ArticleCategoryCreate = 'article_category.create';
    case ArticleCategoryUpdate = 'article_category.update';
    case ArticleCategoryDelete = 'article_category.delete';

    case ContractAssign = 'contract.assign';
    case ContractRevoke = 'contract.revoke';
}
