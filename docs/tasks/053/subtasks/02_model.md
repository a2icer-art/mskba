# Подзадача
Модель данных и ключевые атрибуты.

# EventType
- id, code, label, created_at, updated_at.

# Event
- id, event_type_id, organizer_id, status, title, starts_at, ends_at, timezone (UTC+3), meta (json, optional).

# Tournament
- id, name, starts_at, ends_at, status, organizer_id.

# EventBooking
- id, event_id, venue_id, starts_at, ends_at, status (pending/approved/rejected/cancelled), created_by.

# Примечание
- Специфичные поля типов (если появятся) — отдельные detail-таблицы.

# Статус
[completed]
