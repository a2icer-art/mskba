# step-04
Удаление побочных вызовов из HTTP-слоя.

# Описание
- Удалить вызовы `runIfDue()` из контроллеров и polling-эндпоинтов.

# Выполнено
- Удалены вызовы `BookingPaymentExpiryService::runIfDue()` из HTTP-контроллеров:
  - `app/Http/Controllers/AccountMessagesController.php`
  - `app/Http/Controllers/EventsController.php`
  - `app/Http/Controllers/VenuesController.php`
