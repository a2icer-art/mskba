# step-02
Broadcasting‑инфраструктура.

# Описание
- Добавить конфигурацию broadcasting и каналы.
- Подготовить env‑ключи и зависимости.

# Решение
- Добавлен `config/broadcasting.php` с подключениями `reverb`, `pusher`, `ably`, `log`, `null`.
- Добавлен `routes/channels.php` с каналом `user.{id}`.
- В `routes/web.php` подключены `Broadcast::routes(['middleware' => ['auth']])`.
- Зависимости:
  - `composer.json`: `laravel/reverb`, `pusher/pusher-php-server`
  - `package.json`: `laravel-echo`, `pusher-js`
