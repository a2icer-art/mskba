# task_name
Admin: раздел и меню с доступами по level

# task_status
[100%]

# task_init_description
Создание раздела Admin с проверкой доступов (level > 10). В sidebar выводится меню внутренних страниц, формируемое по level (admin > 30 — полный набор, moderator = 30 — сокращенный, editor = 20 — только контент). Для начала сделать только страницу «Модерация пользователей» с доступом level > 20.

# task_short_description
Сделать раздел Admin с доступом по level, динамическим меню и первой страницей «Модерация пользователей» (level > 20).

# task_full_description


# План выполнения задачи
1. Подготовить базовую структуру раздела Admin: маршруты, контроллер, guard по level > 10, базовый layout и sidebar. (docs/tasks/032/subtasks/step-001.md)
2. Реализовать динамическое формирование меню по level и прокинуть в Inertia. (docs/tasks/032/subtasks/step-002.md)
3. Добавить страницу «Модерация пользователей» с доступом level > 20 и подключить в меню. (docs/tasks/032/subtasks/step-003.md)
