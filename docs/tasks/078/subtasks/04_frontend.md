# step-04
Frontend‑подписки и замена polling.

# Описание
- Настроить Laravel Echo и подписки.
- Заменить polling на realtime обновление данных.

# Решение
- В `resources/js/bootstrap.js` добавлен Echo (Reverb) с `pusher-js` и env‑настройками.
- Создан composable `resources/js/Composables/useMessageRealtime.js` для подписки на `user.{id}`.
- `MainHeader.vue` и `AccountMessagesSettings.vue` переведены на realtime‑счетчик.
- В `AccountMessages.vue` добавлена обработка событий `messages.created` и `messages.read`.
- Polling отключен (таймер не запускается), HTTP‑запросы оставлены только для загрузки истории и действий пользователя.
