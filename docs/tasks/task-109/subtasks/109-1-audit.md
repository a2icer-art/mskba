# 109-1-audit

Цель: зафиксировать текущую механику email-верификации и точки интеграции для Telegram.

## Наблюдения
- ContactVerificationService: requestCode() генерирует 6-значный код, TTL 10 минут, attempts до 5.
- ContactVerification: хранит code, expires_at, sent_at, attempts, verified_at.
- AccountContactsController: /confirm-request и /confirm-verify (для любого контакта).
- AccountPageService: отдаёт contactVerifications (attempts, expires_at).
- Account.vue: универсальный UI подтверждения через код (для email и прочих контактов).

## Выводы
- Нужно расширить модель/сервис под Telegram токены и UI без ввода кода.
- Следует сохранить общую механику, но реализовать отдельный путь подтверждения.
