# Подзадача 101-2: модель настроек

Цель: определить формат хранения пользовательских настроек уведомлений.

Что сделать:
- выбрать хранилище (модель, json в profile/settings или отдельная таблица)
- зафиксировать формат (структура данных, дефолты)
- определить правила валидации

Решение:
- Хранить настройки в отдельной таблице `notification_settings` (per-user), по аналогии с `MessagePrivacySetting`.
- Модель: `App\\Domain\\Notifications\\Models\\NotificationSetting`.
- Поля: `id`, `user_id` (unique), `settings` (json), timestamps.
- Формат `settings`:
  ```
  {
    "booking.status": { "enabled": true, "channels": { "email": [12, 18], "telegram": [], "vk": [], "phone": [], "other": [] } },
    "booking.pending_warning": { "enabled": true, "channels": { ... } },
    "contract.moderation_status": { "enabled": true, "channels": { ... } },
    "contract.assigned": { "enabled": true, "channels": { ... } },
    "contract.revoked": { "enabled": true, "channels": { ... } },
    "contract.permissions_updated": { "enabled": true, "channels": { ... } }
  }
  ```
- Дефолты: `enabled=true` для всех текущих типов, все каналы — пустые массивы.
- При чтении — объединять с дефолтами (чтобы появлялись новые типы).

Выход:
- миграция `create_notification_settings_table`
- модель `NotificationSetting`
- сервис `NotificationSettingsService` для merge дефолтов и update.
