# 106-2-domain

Цель: внедрить правила ролей/лимитов в доменную логику участия.

## Выполнено
- config/events.php: event_type_rules (allowed_roles, limit_role) и paid_roles.
- EventParticipantService: лимит применяется только к limit_role и проверяется allowed_roles.
- EventParticipantRole: добавлены роли seller, staff.
- EventsController: передача allowed_roles/limit_role в UI, счетчики только по limit_role.
