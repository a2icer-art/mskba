# step-03
Backend‑события и payload‑контракты.

# Описание
- Добавить события для новых сообщений, обновления диалогов и счетчиков.
- Описать payload и правила доставки по каналам.

# Решение
- Добавлены события:
  - `App\Domain\Messages\Events\UserMessageCreated` (broadcastAs `messages.created`)
  - `App\Domain\Messages\Events\UserConversationRead` (broadcastAs `messages.read`)
- Канал доставки: `private user.{id}` (через `routes/channels.php`).
- Создан сервис `App\Domain\Messages\Services\MessageRealtimeService` для отправки событий участникам диалога.
- В `MessageService` добавлен вызов realtime‑сервиса после создания сообщений.
- В `AccountMessagesController` добавлена отправка события прочитанности при открытии диалога и при ручном `markRead`.
- В `MessageQueryService` добавлены методы `presentConversation` и `presentMessage` для единых payload‑контрактов.
