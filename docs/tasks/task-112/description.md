# Task 112 — Media domain

Исходный текст задачи:
Вынести медиа в отдельный домен `Media` с полиморфной моделью `Media` для хранения изображений/видео/файлов, связанных с `Venue`, `User`, `Event` и другими сущностями. Реализовать базовый сервис загрузки/удаления и миграцию таблицы.

Краткое описание (task_short_description):
Добавить домен `App\Domain\Media` с моделью `Media`, сервисом `MediaService` (upload/delete), миграцией `create_media_table` и базовым контроллером.

Acceptance criteria:
- Есть модель `App\Domain\Media\Models\Media` с `morphTo` на `mediable`.
- Есть сервис `App\Domain\Media\Services\MediaService` с методами `upload` и `delete`.
- Добавлена миграция `create_media_table`.
- Создан `MediaController` (скелет для использования в маршрутах).
- Задача добавлена в `docs/tasks/dev/todo.md`.
