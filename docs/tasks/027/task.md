# task_name
UserContact: миграция от UserEmail

# task_status
[x]

# task_init_description
Перейти от сущности UserEmail к универсальной UserContact с полем type (enum) для контактов: email, phone, telegram, vk и т.д. Описать шаги миграции и обновить документацию.

# task_short_description
Спланировать миграцию UserEmail -> UserContact (enum type), обновить код/миграции и документацию.

# full_description
Перевести хранение контактов на универсальную сущность `UserContact` с полем `type` (enum) и значением контакта. Мигрировать email из `user_emails`, обновить модели, маршруты, UI страницы аккаунта и документацию.

# План выполнения задачи
1. Добавить доменную модель UserContact, enum типов и миграции с переносом данных. (см. `docs/tasks/027/subtasks/step-001.md`)
2. Обновить backend/Frontend для работы с UserContact и обновить документацию. (см. `docs/tasks/027/subtasks/step-002.md`)
