# task_name
Metro: доменная сущность и сидер станций метро (2025)

# task_status
[100%]

# task_init_description
давай сделаем задачу для добавления Доменной сущности Metro, в модели которой помимо основных атрибутов будет: text name, text alias, text line_name, text line_color, text city, int status default 1, nullable text commentary и создадим seeder который заполнит все станции метро согласно 2025 году

# task_short_description
Metro: доменная сущность и сидер станций метро (актуально на 2025 год).

# task_full_description
Создать доменную сущность Metro с полями: name, alias, line_name, line_color (hex), city, status (default 1), commentary (nullable). Добавить связь 1-to-many с площадками (venues.metro_id). Удалить metro_id из адресов. Добавить сидер станций метро по состоянию на 2025 год на основе открытого источника hh.ru (https://api.hh.ru/metro/1) с сохранением исходных данных в файле database/data/metros_moscow_2025.json.

# План выполнения задачи
1. Спроектировать доменную сущность Metro, миграции и сидер данных. (docs/tasks/041/subtasks/step-001.md)
2. Добавить связь Metro <-> Venue и убрать metro_id из адресов. (docs/tasks/041/subtasks/step-002.md)
3. Обновить формы/отображение площадок с учетом метро. (docs/tasks/041/subtasks/step-003.md)
