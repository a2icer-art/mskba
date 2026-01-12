# task_name
Архитектура прав доступа (permissions)

# task_status
[100%]

# task_init_description
давай сделаем новую задачу  - для введения прав (permissions). Идея заключается в следующем: вероятно, должны быть базовые права,
которые не привязаны к конкретной доменной сущности, например: создание Venue, создание Event (сущность добавится в будущем), создание комментария, простановка рейтинга и тд.
{перечислить вероятные варианты},
а есть права, которые имеют отношение непосредственно к работе с конкретной доменной сущностью - моделью, например: права для работы с конкретным Venue.
На примере Venue: редактировать расписание Venue (сущность VenueSchedule 1-to-1 добавится в будущем), активация/деактивация Venue и тд.
Необходимо спланировать/разработать архитектуру.

# task_short_description
Спланировать архитектуру системы прав доступа: базовые права и права, связанные с конкретными доменными сущностями.

# full_description

## Контекст и требования
- Нужны базовые права, не привязанные к конкретной сущности (global permissions).
- Нужны права, привязанные к конкретной доменной сущности (resource permissions).
- Роли:
  - admin — полный доступ.
  - moderator — только модерационные права.
  - editor — только права на контент (будущие сущности Article, ArticleCategory).

## Модель прав и общая идея
- Вводится домен `Permissions` (или `AccessControl`) как граница для логики прав.
- Два уровня проверки:
  1) Capability-уровень: есть ли право в принципе.
  2) Resource-уровень: можно ли действие с конкретной сущностью (владелец/роль/статус).
- Интеграция с Laravel через Policy/Gate, но решение — доменное.

## Единый реестр прав (PermissionRegistry)
Права описываются единым реестром (enum/registry) с метаданными:
- code (строка права),
- label (человекочитаемое имя),
- scope (global|resource),
- targetModel (для resource).

### Пример базовых global прав
- admin.access — доступ в админ-раздел.
- moderation.access — доступ к модерации.
- logs.view — просмотр логов.
- venue.create — создание площадки.
- event.create — создание события (будущее).
- comment.create — создание комментария.
- rating.create — выставление рейтинга.

### Пример resource прав для Venue
- venue.update — редактирование карточки.
- venue.schedule.manage — управление расписанием (будущее).
- venue.activate — активация.
- venue.deactivate — деактивация.
- venue.block — блокировка.
- venue.media.manage — управление медиа.

### Пример resource прав для контента (Article/ArticleCategory)
- article.create, article.update, article.publish, article.unpublish, article.delete.
- article_category.create, article_category.update, article_category.delete.

## Пресеты ролей (RolePermissionPreset)
- admin: полный набор прав (все global + resource).
- moderator: только модерация:
  - moderation.access, logs.view,
  - venue.activate, venue.deactivate, venue.block,
  - (опционально) venue.media.manage.
- editor: только контент:
  - comment.create, rating.create (если нужно редактору),
  - article.* и article_category.*.

## Хранение назначений
- permissions (реестр): code, label, scope, target_model.
- role_permissions: role_id, permission_id.
- user_permissions: user_id, permission_id (override).
- entity_permissions: permission_id, user_id, entity_type, entity_id (polymorphic).

## Сервисы домена
- PermissionChecker::can(User $user, PermissionCode $code, ?Model $resource = null): bool
- PermissionRegistry: метаданные прав.
- RolePermissionPreset: наборы прав по ролям.

## Интеграция с Laravel
- Policies для ресурсных проверок (VenuePolicy, ArticlePolicy).
- Gates для глобальных прав.
- В контроллерах — только policy/gate, логика внутри домена.

## Риски и допущения
- Article и ArticleCategory — будущие сущности, права фиксируются заранее.
- Вопрос: нужны ли editor права на admin.access? По умолчанию — нет.

## План выполнения задачи
1. Согласовать финальный перечень прав и пресетов ролей.
2. Описать домен Permissions (классы, таблицы, сервисы, интеграция с Policy/Gate).
3. Подготовить минимальный план реализации (миграции, сидеры, примеры политики).
