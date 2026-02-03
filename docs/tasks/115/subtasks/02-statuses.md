# Подзадача 02: Статусы и переходы

## Статусы подтверждения оплаты
- `none`
- `user_paid_pending`
- `user_paid_rejected`
- `admin_confirmed`

## Переходы
- Организатор: `none` → `user_paid_pending` (с доказательством).
- Администратор: `user_paid_pending` → `admin_confirmed` или `user_paid_rejected`.
- Повторный запрос: `user_paid_rejected` → `user_paid_pending`.

См. `docs/project/event_organizer_payment_flow.md` (раздел «Статусы»).
