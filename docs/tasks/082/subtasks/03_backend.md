# step-03
Backend-правки.

# Изменения
- Добавлен сервис `BookingPendingExpiryService` с гибридной логикой автоотмены pending и предупреждений.
- Добавлен job `RunBookingPendingExpiryJob` и команда `bookings:pending-expire`.
- Планировщик запускает job каждую минуту.
- Расширены настройки площадки и defaults в `VenueSettings`.
- Добавлена миграция на новые поля `pending_*` в `venue_settings`.
- В `BookingNotificationService` добавлено системное предупреждение о скорой автоотмене.
