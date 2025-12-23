# task_init_description
Разработать доменную сущность Place
Place (место проведения: зал, площадка и тд).
Атрибуты: int id (inc), string name, unique string alias, enum status (unconfirmed, moderation, confirmed), dates (стандартные Laravel: кто создал, когда и тд), int created_by, datetime nullable confirmed_at, int nullable confirmed_by (fk -> users), int nullable place_type_id (fk -> place_types), string nullable address, int nullable address_id (fk -> addresses), bool is_deleted default 0, int deleted_by, datetime deleted_at

# уточнения
- Статус: использовать unconfirmed (исправлена опечатка).
- Soft delete: используем стандартный SoftDeletes Laravel + поля deleted_by/is_deleted по необходимости.
- Auditing: предусмотреть created_by/updated_by и timestamps created_at/updated_at.
- Alias: формировать SEF/ЧПУ от названия (slug).
- Place type: указать обязательность поля и нужны ли seed-данные/enum.

# task_short_description
Создать доменную сущность Place с указанными атрибутами и связями.
