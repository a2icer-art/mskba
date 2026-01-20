# step-01
Аудит текущих вызовов обновления статусов бронирований и условий автоматических переходов.

# Описание
- Зафиксировать все места, где вызывается `BookingPaymentExpiryService::runIfDue()` и `cancelIfExpired()`.
- Определить, какие статусы должны обновляться по расписанию.

# Результат аудита
## Точки вызова `runIfDue()`
- `app/Http/Controllers/AccountMessagesController.php:169` (polling сообщений).
- `app/Http/Controllers/EventsController.php:29` (список событий).
- `app/Http/Controllers/EventsController.php:243` (карточка события).
- `app/Http/Controllers/VenuesController.php:470` (страницы площадки, логика расписания).

## Точки вызова `cancelIfExpired()`
- `app/Http/Controllers/VenuesController.php:812` (действия с бронированием, отмена по просрочке).
- `app/Http/Controllers/VenuesController.php:919` (действия с бронированием, отмена по просрочке).

## Текущая логика автопереходов (BookingPaymentExpiryService)
- Срабатывает только для статуса `awaiting_payment` при `payment_due_at <= now()`.
- Дросселирование: 30 секунд через cache key `booking_payment_expiry_throttle`.
- Пакетная обработка: максимум 50 бронирований за запуск.
- Действия при отмене: перевод статуса в `cancelled`, проставление авто-модерации, отмена платежей в статусах `created/pending`, отправка системного уведомления через `BookingNotificationService`.

## Замечания
- Scheduler в проекте не настроен (в `routes/console.php` только пример `inspire`).
- Логика зависит от HTTP-трафика (polling/страницы), что и нужно убрать.
