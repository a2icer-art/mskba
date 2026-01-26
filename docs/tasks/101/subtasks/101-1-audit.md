# Подзадача 101-1: аудит уведомлений

Цель: собрать все типы уведомлений/системных сообщений, которые уже отправляются в проекте.

Что проверить:
- сервисы уведомлений/сообщений (contracts, bookings и т.д.)
- системные уведомления в доменах
- любые дополнительные источники уведомлений

Результат (по коду):
- Бронирования: смена статуса (Pending/Approved/AwaitingPayment/Paid/Cancelled) — `app/Domain/Events/Services/BookingNotificationService.php` (notifyStatus), вызывается из `app/Http/Controllers/VenuesController.php`, `app/Domain/Events/Services/EventBookingService.php`, `app/Domain/Events/Services/BookingPendingExpiryService.php`, `app/Domain/Events/Services/BookingPaymentExpiryService.php`.
- Бронирования: предупреждение об авто-отмене pending — `app/Domain/Events/Services/BookingNotificationService.php` (notifyPendingWarning), вызывается из `app/Domain/Events/Services/BookingPendingExpiryService.php`.
- Контракты: статус модерации — `app/Domain/Contracts/Services/ContractNotificationService.php` (notifyModerationStatus), вызывается из `app/Http/Controllers/AdminContractsModerationController.php`.
- Контракты: назначение — `app/Domain/Contracts/Services/ContractNotificationService.php` (notifyContractAssigned), вызывается из `app/Http/Controllers/AdminContractsModerationController.php`, `app/Http/Controllers/VenuesController.php`.
- Контракты: аннулирование — `app/Domain/Contracts/Services/ContractNotificationService.php` (notifyContractRevoked), вызывается из `app/Http/Controllers/AdminContractsModerationController.php`, `app/Http/Controllers/VenuesController.php`.
- Контракты: изменение прав — `app/Domain/Contracts/Services/ContractNotificationService.php` (notifyContractPermissionsUpdated), вызывается из `app/Http/Controllers/VenuesController.php`.

Примечание:
- Дополнительных системных уведомлений через `NotificationService` в коде не обнаружено.
