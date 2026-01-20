# step-01
Аудит текущего flow оплаты/подтверждения.

# Описание
- Проверить текущие маршруты, методы и UI для принятия в работу, ожидания оплаты, подтверждения.
- Зафиксировать текущие статусы и ограничения.

# Результат
- Backend:
  - `awaitPaymentBooking` (`app/Http/Controllers/VenuesController.php`): только для `Pending`, берет `payment_order` из настроек площадки, ставит `AwaitingPayment`, рассчитывает сумму и ставит `Payment` в `Pending`, срок оплаты — `payment_wait_minutes`.
  - `markPaidBooking`: только для `AwaitingPayment`, переводит `Payment` в `Paid` и бронь в `Paid/Approved` в зависимости от `booking_mode`.
  - `confirmBooking`: подтверждает только после `Paid`.
- UI:
  - `/venues/<type>/<alias>/bookings` (`resources/js/Pages/VenueBookings.vue`) — кнопки: «В оплату», «Оплачено», «Подтвердить», модалки только с комментарием.
  - Полей выбора порядка оплаты/срока ожидания/частичной суммы нет.
- Модель:
  - У заявки есть `payment_order_id`, `payment_order_snapshot`, `payment_due_at` (`app/Domain/Events/Models/EventBooking.php`).
  - Частичная предоплата нигде не учитывается; `Payment.amount_minor` всегда полная сумма.
