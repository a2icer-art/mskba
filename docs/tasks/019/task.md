# task_name
User: источник регистрации

# task_status
[x]

# task_init_description
Для модели User добавить registered_via enum default site (site, tg_link, email_link, other), nullable text/json registration_details — чтобы понимать, как пользователь зарегистрировался.

# task_short_description
Добавить в User поля registered_via и registration_details для фиксации источника регистрации.

# full_description
Добавить хранение источника регистрации пользователя, чтобы фиксировать канал регистрации и доп. данные (ссылка, метка, кампания и т.п.).

# План выполнения задачи
1. Добавить enum UserRegisteredVia и миграцию для полей registered_via и registration_details. (см. `subtasks/step-001.md`)
2. Обновить модель User, фабрику и регистрацию, чтобы учитывать registered_via. (см. `subtasks/step-002.md`)
3. Проверить сиды и зафиксировать изменения в документации задачи. (см. `subtasks/step-003.md`)
