# Подзадача
Доменные сущности и связи.

# Решение
- EventType: справочник типов событий (game, training, game_training).
- Event: базовая сущность события (тип, организатор, статус, тайминг).
- Tournament: коллекция событий типа game (агрегат или контейнер).
- EventBooking (EventSlot): связь события с площадкой и интервалом времени.

# Связи
- Event belongsTo EventType.
- Event belongsTo Organizer (User).
- Event hasMany EventBooking.
- EventBooking belongsTo Event and Venue.
- Tournament hasMany Event.

# Статус
[completed]
