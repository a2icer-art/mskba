# Подзадача 101-3: backend

Цель: подготовить API/контроллеры для получения и сохранения настроек пользователя.

Что сделать:
- добавить/расширить контроллер /account
- сервис для чтения/сохранения настроек
- валидация

Выполнено:
- Миграция: `database/migrations/2026_01_26_120000_create_notification_settings_table.php`.
- Модель: `app/Domain/Notifications/Models/NotificationSetting.php`.
- Enum кодов: `app/Domain/Notifications/Enums/NotificationCode.php`.
- Сервис настроек: `app/Domain/Notifications/Services/NotificationSettingsService.php`.
- Контроллер: `app/Http/Controllers/AccountNotificationsController.php`.
- Роуты: `GET /account/settings/notifications`, `PATCH /account/settings/notifications` в `routes/web.php`.

Данные для UI:
- `definitions` (типы уведомлений)
- `settings` (настройки пользователя с дефолтами)
- `channels` (доступные каналы)
- `contacts` (значение контакта + confirmed)
