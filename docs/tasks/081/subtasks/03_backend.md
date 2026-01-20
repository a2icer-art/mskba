# step-03
Backend-правки.

# Итог
- Обновил бекенд-обработку «принять в работу»: выбор порядка оплаты, срок ожидания, частичная сумма.
- Уточнил условия доступности действий (подтверждение/оплата) под постоплату.
- В выдачу бронирований добавлены данные для UI (порядок оплаты, дефолты).

# Изменения
- `app/Http/Controllers/VenuesController.php`: метод `bookings` теперь подтягивает настройки площадки,
  список порядков оплаты и отдает `paymentOrderOptions` + `paymentDefaults`. В выдаче бронирования
  добавлены `payment_order_id` и `payment_order_code`, а также скорректированы флаги `can_*`
  с учетом постоплаты.
- `app/Http/Controllers/VenuesController.php`: `awaitPaymentBooking` принимает `payment_order_id`,
  `payment_wait_minutes`, `partial_amount_minor`, считает сумму/сроки и сохраняет снимок порядка оплаты.
  Для постоплаты статус остается `pending`, уведомление не отправляется.
- `app/Http/Controllers/VenuesController.php`: `confirmBooking` и `markPaidBooking` разрешают действия
  для постоплаты в состояниях `pending`/`approved` соответственно.

# Правила
- Порядок оплаты можно изменить только до оплаты (статусы `pending` и `awaiting_payment`).
- При постоплате перевод в «ожидание оплаты» не выполняется — остается `pending`,
  подтверждение выполняется отдельным действием.
