# step-05
Проверка и документация.

# Описание
- Проверить сценарии авто-статусов на тестовых данных.
- Зафиксировать итоговые изменения и инструкции запуска scheduler/job.

# Инструкции запуска
- Ручной запуск: `php artisan bookings:expire`
- Запуск по расписанию: настроить cron на сервере
  - `* * * * * php /path/to/project/artisan schedule:run >> /dev/null 2>&1`
- Для исполнения `RunBookingPaymentExpiryJob` требуется запущенный queue worker.

# Локальный запуск (dev)
- В отдельном процессе: `php artisan schedule:work`
- В отдельном процессе: `php artisan queue:work`

# Продакшен (VPS)
- `schedule:run` через cron.
- `queue:work` как сервис (supervisor/systemd).

# Проверка
- Создать бронирование в статусе `awaiting_payment` с `payment_due_at` в прошлом.
- Выполнить `php artisan bookings:expire`.
- Убедиться, что:
  - статус бронирования стал `cancelled`;
  - платежи в статусах `created/pending` переведены в `cancelled`;
  - отправлено системное уведомление о статусе.

# Результат тестов
- `php artisan test --filter=BookingPaymentExpiryTest` — 2 passed.

# UI‑проверка
- На локальном стенде подтверждено: по истечению `payment_due_at` статус брони меняется автоматически.
