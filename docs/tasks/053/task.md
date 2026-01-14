# task_name
Проектирование домена Events

# task_status
[x]

# task_init_description
нужно спроектировать домен Events: базовая сущность Event, справочник EventType (game, training, game_training), турнир как коллекция событий. Также определить флоу бронирования площадки через событие.

# task_short_description
Спроектировать домен Events (Event, EventType, Tournament) и флоу бронирований через событие.

# task_full_description
Нужно детально спроектировать домен Events с базовой сущностью Event и справочником EventType (game, training, game_training). Турнир — как коллекция событий типа game. Бронирование площадки должно быть связано с событием: нельзя бронировать без Event. Требуется описать модель данных, связи между Event, Venue, EventBooking (EventSlot), а также основной флоу создания события и бронирования с проверкой расписания и конфликтов. Решение должно быть минимальным и расширяемым, с возможностью добавить специфичные атрибуты типов в будущем.

# План выполнения задачи
1. Описать доменные сущности и их связи (Event, EventType, Tournament, EventBooking). (subtasks/01_domain.md)
2. Определить модель данных и ключевые атрибуты (минимальный набор). (subtasks/02_model.md)
3. Спроектировать флоу бронирования через событие и статусы. (subtasks/03_flow.md)
4. Определить права доступа и роли в сценариях. (subtasks/04_permissions.md)
5. Определить сидер для базовых типов EventType (game, training, game_training). (subtasks/05_seeder.md)
6. Зафиксировать решение в документации проекта. (subtasks/06_docs.md)
7. Реализовать модели, миграции и сидер для домена Events. (subtasks/07_implementation.md)
