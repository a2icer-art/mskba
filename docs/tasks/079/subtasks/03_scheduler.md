# step-03
Команда и расписание scheduler.

# Описание
- Добавить консольную команду.
- Настроить расписание в `routes/console.php` с защитой от перекрытий.

# Решение
- Команда: `bookings:expire` (выполняет `RunBookingPaymentExpiryJob::dispatchSync()`).
- Расписание: `Schedule::job(new RunBookingPaymentExpiryJob())->everyMinute()->withoutOverlapping(1)`.
