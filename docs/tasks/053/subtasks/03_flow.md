# Подзадача
Флоу бронирования через событие.

# Флоу
1) Создание Event (выбор типа, базовых данных).
2) Создание EventBooking (выбор площадки + интервал).
3) Проверка доступности: VenueSchedule + отсутствие пересечений бронирований.
4) Подтверждение/отмена брони (владелец площадки или админ).

# Статусы
- Event: draft -> scheduled -> completed/cancelled.
- EventBooking: pending -> approved/rejected/cancelled.

# Статус
[completed]
