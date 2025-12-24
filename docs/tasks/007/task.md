# task_name
Обновить доменные сущности Users по новым правилам

# task_status
[x]

# task_init_description
Обновить доменные сущности User, UserRole, Role, UserProfile с учетом новых правил (SoftDeletes, auditing и связанных полей).

# task_short_description
Привести сущности Users в соответствие с общими правилами SoftDeletes и auditing.

# full_description
Обновить модели User, UserRole, Role и UserProfile в соответствии с новыми конвенциями: SoftDeletes (deleted_at и deleted_by), auditing (created_by/updated_by), уточнить fillable/casts/relations и соответствующие миграции при необходимости.

# План выполнения задачи
1. Проанализировать текущие модели и миграции User, UserRole, Role, UserProfile и зафиксировать недостающие поля по правилам SoftDeletes/auditing. (см. `subtasks/step-001.md`)
2. Добавить нужные поля/связи и обновить модели (fillable/casts/relations, SoftDeletes). (см. `subtasks/step-002.md`)
3. Обновить сидеры/фабрики при необходимости и отметить статус задачи. (см. `subtasks/step-003.md`)

