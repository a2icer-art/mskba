# Task 112 — Выполнение: Media domain

task_name: Добавить домен Media с полиморфными медиа (Venue/User/Event и т.д.)

task_status: in-progress

task_init_description:
Вынести медиа в отдельный домен `Media` с полиморфной моделью `Media` для хранения изображений/видео/файлов, связанных с `Venue`, `User`, `Event` и другими сущностями.

Plan:
1. Добавить модель `Media` в `App\Domain\Media\Models`.
2. Добавить `MediaService` с методами `upload`/`delete`.
3. Добавить миграцию `create_media_table`.
4. Создать базовый `MediaController` и пример маршрута (добавить в `routes/web.php` по согласованию).
5. Обновить документацию задачи и пометить задачу в `todo.md`.

Subtasks:
- backend: модель + миграция + сервис
- backend: контроллер + (опционально) маршруты
- docs: описание задачи и критерии приёмки
