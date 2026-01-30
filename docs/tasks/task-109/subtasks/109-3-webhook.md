# 109-3-webhook

Цель: реализовать webhook Telegram и обработку callback подтверждения.

## Требования
- Обработчик /telegram/webhook.
- Проверка токена по хэшу и TTL.
- Подтверждение контакта и фиксация verified_at.
- Безопасность: валидация секретов/токенов где возможно.

## Примечания
- Бот @MSKBABot.
- Deep-link: https://t.me/<bot>?start=<token>.

## Реализация
- Webhook маршрут: POST /telegram/webhook (без CSRF).
- Проверка секретного токена из заголовка X-Telegram-Bot-Api-Secret-Token (если задан TELEGRAM_WEBHOOK_SECRET).
- Обработка /start <token>: бот отправляет сообщение с inline-кнопкой «Подтвердить».
- Callback confirm:<token>: валидация токена и подтверждение контакта.
