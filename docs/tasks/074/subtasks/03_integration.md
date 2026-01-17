# 03_integration.md
- Подключение уведомлений во все изменения статусов бронирования.
- Учитывать ручные и автоматические переходы.
- Подключить:
  - `EventBookingService::create` → `pending`.
  - `VenuesController::awaitPaymentBooking` → `awaiting_payment`.
  - `VenuesController::markPaidBooking` → `paid` или `approved` (в зависимости от booking_mode).
  - `VenuesController::confirmBooking` → `approved`.
  - `VenuesController::cancelBooking` → `cancelled`.
  - `BookingPaymentExpiryService` (auto‑cancel) → `cancelled`.
