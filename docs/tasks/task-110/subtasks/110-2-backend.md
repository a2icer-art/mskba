# 110-2-backend

Цель: реализовать backend для списка пользователей и сброса подтверждения контактов.

## Реализация
- Контроллер: AdminUsersController с фильтрами (q, status, role, registered_via, registered_from/to).
- Данные: роли пользователя, роли участника, статус, статус‑изменен (confirmed_at/blocked_at), зарегистрирован, registered_via.
- Endpoint сброса подтверждения контакта: confirmed_at -> null + удаление активных верификаций.
- Endpoints обновления ролей:
  - /admin/users/{user}/roles (системные роли)
  - /admin/users/{user}/participant-roles (роли участника)
- Маршруты: /admin/users, /admin/users/{user}/contacts/{contact}/reset-confirmation.
- Доступ: только admin (can:admin.access).
