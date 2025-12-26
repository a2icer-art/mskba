# task_init_description
Перейти от сущности UserEmail к универсальной UserContact с полем type (enum) для контактов: email, phone, telegram, vk и т.д. Описать шаги миграции и обновить документацию.

# task_short_description
Спланировать миграцию UserEmail -> UserContact (enum type), обновить код/миграции и документацию.

# task_migration_steps
1. Добавить enum типов контактов (email/phone/telegram/vk/other).
2. Создать таблицу `user_contacts` (user_id, type, value, confirmed_at, auditing).
3. Подготовить миграцию переноса данных из `user_emails` в `user_contacts` (type=email, value=email).
4. Обновить модели и связи в `User` и домене Users.
5. Обновить UI/маршруты/валидации на работе с `UserContact`.
6. Удалить `user_emails` и связанные миграции/код, либо оставить deprecated на период миграции.
7. Обновить документацию в `docs/project`.
