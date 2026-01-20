# step-01
Аудит текущего polling и точек обновления.

# Описание
- Зафиксировать все места polling в UI и backend.
- Описать, какие данные обновляются и где они отображаются.

# Результат аудита
## UI: polling
- `resources/js/Composables/useMessagePolling.js` — общий composable с `setInterval` (5s по умолчанию).
- `resources/js/Pages/AccountMessages.vue`:
  - poll `/account/messages/poll` (сообщения, диалоги, счетчики, мета).
  - обновление списка диалогов, активного диалога, сообщений, мета, счетчика непрочитанных.
  - polling используется как для живого обновления, так и для загрузки старых сообщений.
- `resources/js/Pages/AccountMessagesSettings.vue`:
  - poll `/account/messages/poll` только для счетчика непрочитанных (badge в sidebar).
- `resources/js/Components/MainHeader.vue`:
  - poll `/account/messages/poll` только для счетчика непрочитанных (badge в header).

## Backend: polling endpoint
- `app/Http/Controllers/AccountMessagesController.php::poll()`
  - возвращает `unread_messages`, `conversations`, `messages`, `messages_meta`, `messages_mode`,
    `messages_refresh`, `active_conversation` (по параметрам запроса).

## Смежные участки
- `resources/js/Pages/Account.vue` использует `setInterval` для локальных UI‑таймеров (контакты/коды),
  это не связано с polling сообщений и не должно меняться в рамках задачи 078.
