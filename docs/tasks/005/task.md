# task_name
Демо-данные: роли, пользователи, профили (seeders/factories)

# task_status
[x]

# task_init_description
#execute_task:005[expand_task, plan_task, execute, set_done]
давай переработаем начальные демо данные (seeder, fabric):
roles: admin, moderator, editor
users: admin (login: admin, password: 123) - roles: admin, moderator (login: moderator, password: 123) - roles: moderator, editor (login: editor, password: 123) - roles: editor, supereditor (login: supereditor, password: 123) - roles: (moderator, editor)
и seeder/fabric для создания связанных профилей по твоему усмотрению

# task_short_description
Переработать демо-данные: роли, пользователи с ролями и фабрики/сидеры для связанных профилей.

# full_description
Обновить сидеры и фабрики для демонстрационных данных: роли `admin`, `moderator`, `editor`, пользователи с заданными логинами и паролем `123`, привязки ролей к пользователям, а также создание связанных профилей пользователей через фабрики/сидеры.

# План выполнения задачи
1. Обновить сидер `database/seeders/DatabaseSeeder.php`: создать роли `admin`/`moderator`/`editor`, пользователей с логинами и паролями `123`, привязать роли и создать профили.
2. Минимально расширить фабрики в `database/factories/*.php` (состояния/хелперы), чтобы сидер был читабельным и повторяемым.
3. Проверить модели/связи, чтобы создание профилей и ролей соответствовало текущим отношениям.
