# Подзадача
Модель данных: сущности, связи с Venue, формат интервалов.

# Решение
- VenueSchedule (1:1 с Venue).
- VenueScheduleInterval (еженедельные интервалы по дням недели).
- VenueScheduleException (исключение на конкретную дату).
- VenueScheduleExceptionInterval (интервалы для даты-исключения).

# Поля (предложение)
- VenueSchedule: id, venue_id (unique), timezone (строка, фикс. UTC+3), created_at, updated_at.
- VenueScheduleInterval: id, schedule_id, day_of_week (1-7), starts_at (time), ends_at (time), created_at, updated_at.
- VenueScheduleException: id, schedule_id, date (date), is_closed (bool), comment (nullable), created_at, updated_at.
- VenueScheduleExceptionInterval: id, exception_id, starts_at (time), ends_at (time), created_at, updated_at.

# Связи
- Venue hasOne VenueSchedule.
- VenueSchedule hasMany VenueScheduleInterval.
- VenueSchedule hasMany VenueScheduleException.
- VenueScheduleException hasMany VenueScheduleExceptionInterval.

# Статус
[completed]
