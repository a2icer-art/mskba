# task_name
Доменная сущность Venue

# task_status
[x]

# task_init_description
Разработать доменную сущность Venue
Venue (место проведения: зал, площадка и тд).
Атрибуты: int id (inc), string name, unique string alias, enum status (unconfirmed, moderation, confirmed), dates (стандартные Laravel: кто создал, когда и тд), int created_by, datetime nullable confirmed_at, int nullable confirmed_by (fk -> users), int nullable venue_type_id (fk -> venue_types), string nullable address, int nullable address_id (fk -> addresses), bool is_deleted default 0, int deleted_by, datetime deleted_at

# уточнения
- Статус: использовать unconfirmed (исправлена опечатка).
- Soft delete: используем стандартный SoftDeletes Laravel + поле deleted_by.
- Auditing: предусмотреть created_by/updated_by и timestamps created_at/updated_at.
- Alias: формировать SEF/ЧПУ от названия (slug).
- Venue type: поле обязательное; создать сущность/таблицу venue_types и заполнить seed-данными/enum.

# task_short_description
Создать доменную сущность Venue с указанными атрибутами и связями.

# full_description

# План выполнения задачи
1. Подготовить домен `Venue`: модели/enum, миграции для `venues` и `venue_types`, связи с `users`, `addresses`. (см. `subtasks/step-001.md`)
2. Добавить сидеры/фабрики для `venue_types` и базовых `venues` (если нужно), с учётом alias/slug и auditing. (см. `subtasks/step-002.md`)
3. Подключить в `DatabaseSeeder`, проверить целостность/ограничения, обновить статус в `task.md`. (см. `subtasks/step-003.md`)

