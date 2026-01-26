# Подзадача 101-5: доставка уведомлений

Цель: подключить настройки к отправке уведомлений и внешним каналам.

Что сделать:
- учитывать выключенные типы
- дублировать на внешние каналы при включении
- использовать подтвержденные контакты

Выполнено:
- Добавлен сервис `app/Domain/Notifications/Services/NotificationDeliveryService.php` для отправки во внешние каналы (email).
- Обновлены `app/Domain/Events/Services/BookingNotificationService.php` и `app/Domain/Contracts/Services/ContractNotificationService.php`:
  - учет `enabled` по каждому пользователю;
  - отправка внешних каналов по настройкам пользователя;
  - создание receipts только для разрешенных получателей.
- Обновлен `app/Domain/Messages/Services/MessageService.php` — опциональный список получателей для системных сообщений.
