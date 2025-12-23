# task_name
Начальная структура доменных сущностей пользователей

# task_status
[x]

# task_init_description
первая задача - разработать начальную структуру проекта: для доменных сущностей UserProfile (1-to-1 User-UserProfile), Roles, UserRole (1-to-many User-Roles)

# task_short_description
Определить начальную структуру доменных сущностей пользователей и их связей (UserProfile 1-to-1 с User, Roles и UserRole 1-to-many).

# full_description
Нужно разработать начальный каркас доменных сущностей пользователей и их связей в рамках DDD-модуля. Включить профиль пользователя (UserProfile) в отношении 1-to-1 с User, роли (Roles) и связующую сущность UserRole для связи пользователя с ролями в отношении 1-to-many. Для User добавить поля: enum status (default unconfirmed/confirmed), confirmed_at (nullable timestamp), confirmed_by (nullable enum: admin, email, phone, telegram, vk, other), commentary (nullable). Для UserProfile добавить поля: first_name, last_name, middle_name, gender, birth_date. Для Roles добавить поля: name, alias, status, commentary (nullable). Изменения должны быть минимальными и локальными, без лишнего рефакторинга, с опорой на текущую структуру проекта.

# plan
1. Актуализировать описание задачи и зафиксировать перечень полей/enum для User, UserProfile и Roles — `subtasks/step-001.md`.
2. Подготовить миграции и enum-описания для User и Roles, включая новые поля — `subtasks/step-002.md`.
3. Обновить модели домена и связи, добавить fillable/casts — `subtasks/step-003.md`.
