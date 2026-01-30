# 109-2-token-flow

Цель: описать и реализовать генерацию/хранение токенов для Telegram и выдачу deep-link.

## Требования
- Одноразовый токен 128+ бит.
- Хранить только хэш токена в БД.
- Привязка к user_id и contact_id.
- TTL и политика повторной выдачи.

## План
- Добавить поля для token_hash и token_expires_at (или использовать отдельную сущность).
- Реализовать генерацию и сохранение токена в ContactVerificationService.
- Возвращать deep-link для UI.

## Реализация
- В contact_verifications добавлено поле token_hash (64) и индекс.
- Telegram токен генерируется как base64url(32 байта), хэшируется sha256.
- TTL токена: 15 минут (используется expires_at).
- Для Telegram создаётся запись с code = 'telegram', token_hash и expires_at.
- Deep-link формируется по config('services.telegram.bot_username').
