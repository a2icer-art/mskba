# task_name
User: перенос email в UserEmail и удаление name

# task_status
[x]

# task_init_description
Вероятно из модели User можно убрать поле name, так как оно перенесено в UserProfile.

# task_short_description
Удалить поле name из User и перенести email в UserEmail с обновлением регистрации и UI.

# full_description
Удалить поля `name` и `email` из `users`. Добавить `UserEmail` (1:N) с хранением подтверждения. Обновить регистрацию пользователя, отображение аккаунта и связанные процессы (миграции, сидеры, фабрики, shared props). Зафиксировать результат в проектной документации.

# План выполнения задачи
1. Проанализировать использование `name` и `email` в коде и БД, определить изменения. (см. `docs/tasks/023/subtasks/step-001.md`)
2. Перенести email в `UserEmail`, удалить `name`/`email` из `users`, обновить регистрацию и UI. (см. `docs/tasks/023/subtasks/step-002.md`)
