<?php

namespace App\Domain\Permissions\Registry;

use App\Domain\Permissions\Enums\PermissionCode;
use App\Domain\Permissions\Enums\PermissionScope;
use App\Domain\Venues\Models\Venue;

class PermissionRegistry
{
    public static function all(): array
    {
        return [
            [
                'code' => PermissionCode::AdminAccess,
                'label' => 'Доступ в админ-раздел',
                'scope' => PermissionScope::Global,
                'target_model' => null,
            ],
            [
                'code' => PermissionCode::ModerationAccess,
                'label' => 'Доступ к модерации',
                'scope' => PermissionScope::Global,
                'target_model' => null,
            ],
            [
                'code' => PermissionCode::LogsView,
                'label' => 'Просмотр логов',
                'scope' => PermissionScope::Global,
                'target_model' => null,
            ],
            [
                'code' => PermissionCode::VenueCreate,
                'label' => 'Создание площадки',
                'scope' => PermissionScope::Global,
                'target_model' => null,
            ],
            [
                'code' => PermissionCode::VenueBooking,
                'label' => 'Бронирование площадки',
                'scope' => PermissionScope::Global,
                'target_model' => null,
            ],
            [
                'code' => PermissionCode::EventCreate,
                'label' => 'Создание события',
                'scope' => PermissionScope::Global,
                'target_model' => null,
            ],
            [
                'code' => PermissionCode::CommentCreate,
                'label' => 'Создание комментария',
                'scope' => PermissionScope::Global,
                'target_model' => null,
            ],
            [
                'code' => PermissionCode::RatingCreate,
                'label' => 'Выставление рейтинга',
                'scope' => PermissionScope::Global,
                'target_model' => null,
            ],
            [
                'code' => PermissionCode::VenueUpdate,
                'label' => 'Редактирование площадки',
                'scope' => PermissionScope::Resource,
                'target_model' => Venue::class,
            ],
            [
                'code' => PermissionCode::VenueSubmitForModeration,
                'label' => 'Отправка площадки на модерацию',
                'scope' => PermissionScope::Resource,
                'target_model' => Venue::class,
            ],
            [
                'code' => PermissionCode::VenueScheduleManage,
                'label' => 'Управление расписанием площадки',
                'scope' => PermissionScope::Resource,
                'target_model' => Venue::class,
            ],
            [
                'code' => PermissionCode::VenueBookingConfirm,
                'label' => 'Подтверждение бронирования площадки',
                'scope' => PermissionScope::Resource,
                'target_model' => Venue::class,
            ],
            [
                'code' => PermissionCode::VenueBookingCancel,
                'label' => 'Отмена бронирования площадки',
                'scope' => PermissionScope::Resource,
                'target_model' => Venue::class,
            ],
            [
                'code' => PermissionCode::VenueActivate,
                'label' => 'Активация площадки',
                'scope' => PermissionScope::Resource,
                'target_model' => Venue::class,
            ],
            [
                'code' => PermissionCode::VenueDeactivate,
                'label' => 'Деактивация площадки',
                'scope' => PermissionScope::Resource,
                'target_model' => Venue::class,
            ],
            [
                'code' => PermissionCode::VenueBlock,
                'label' => 'Блокировка площадки',
                'scope' => PermissionScope::Resource,
                'target_model' => Venue::class,
            ],
            [
                'code' => PermissionCode::VenueMediaManage,
                'label' => 'Управление медиа площадки',
                'scope' => PermissionScope::Resource,
                'target_model' => Venue::class,
            ],
            [
                'code' => PermissionCode::ContractAssign,
                'label' => 'Назначение контрактов',
                'scope' => PermissionScope::Resource,
                'target_model' => Venue::class,
            ],
            [
                'code' => PermissionCode::ContractRevoke,
                'label' => 'Аннулирование контрактов',
                'scope' => PermissionScope::Resource,
                'target_model' => Venue::class,
            ],
            [
                'code' => PermissionCode::ContractPermissionsUpdate,
                'label' => 'Редактирование прав контрактов',
                'scope' => PermissionScope::Resource,
                'target_model' => Venue::class,
            ],
            [
                'code' => PermissionCode::ArticleCreate,
                'label' => 'Создание статьи',
                'scope' => PermissionScope::Resource,
                'target_model' => 'App\\Domain\\Content\\Models\\Article',
            ],
            [
                'code' => PermissionCode::ArticleUpdate,
                'label' => 'Редактирование статьи',
                'scope' => PermissionScope::Resource,
                'target_model' => 'App\\Domain\\Content\\Models\\Article',
            ],
            [
                'code' => PermissionCode::ArticlePublish,
                'label' => 'Публикация статьи',
                'scope' => PermissionScope::Resource,
                'target_model' => 'App\\Domain\\Content\\Models\\Article',
            ],
            [
                'code' => PermissionCode::ArticleUnpublish,
                'label' => 'Снятие статьи с публикации',
                'scope' => PermissionScope::Resource,
                'target_model' => 'App\\Domain\\Content\\Models\\Article',
            ],
            [
                'code' => PermissionCode::ArticleDelete,
                'label' => 'Удаление статьи',
                'scope' => PermissionScope::Resource,
                'target_model' => 'App\\Domain\\Content\\Models\\Article',
            ],
            [
                'code' => PermissionCode::ArticleCategoryCreate,
                'label' => 'Создание категории статей',
                'scope' => PermissionScope::Resource,
                'target_model' => 'App\\Domain\\Content\\Models\\ArticleCategory',
            ],
            [
                'code' => PermissionCode::ArticleCategoryUpdate,
                'label' => 'Редактирование категории статей',
                'scope' => PermissionScope::Resource,
                'target_model' => 'App\\Domain\\Content\\Models\\ArticleCategory',
            ],
            [
                'code' => PermissionCode::ArticleCategoryDelete,
                'label' => 'Удаление категории статей',
                'scope' => PermissionScope::Resource,
                'target_model' => 'App\\Domain\\Content\\Models\\ArticleCategory',
            ],
        ];
    }

    public static function codes(): array
    {
        return array_map(static fn (array $item) => $item['code'], self::all());
    }
}
