# 105-1-audit

Цель: зафиксировать текущее состояние реализации по задаче 105.

## Модель и миграции
- app/Domain/Events/Models/EventParticipant.php
- app/Domain/Events/Enums/EventParticipantRole.php
- app/Domain/Events/Enums/EventParticipantStatus.php
- database/migrations/2026_01_27_030000_create_event_participants_table.php
- database/migrations/2026_01_27_040000_add_status_change_fields_to_event_participants_table.php
- database/migrations/2026_01_27_050000_add_user_status_reason_to_event_participants_table.php

## Сервисы и правила
- app/Domain/Events/Services/EventParticipantService.php
  - сценарии invite/join/respond/changeStatus
  - лимит учитывается для confirmed, при превышении -> reserve

## Контроллеры и маршруты
- app/Http/Controllers/EventsController.php
  - выдача участников, счетчиков confirmed/reserve
  - обработчики invite/join/respond/status
  - блокировка «повышения» статуса при изменении организатором
- app/Http/Controllers/Integrations/UserSuggestController.php
  - фильтрация подсказок по роли участника
- routes/web.php
  - /events/{event}/participants/*

## UI
- resources/js/Pages/EventShow.vue
  - блок участников (админ/создатель), приглашения, смена статуса с причиной
- resources/js/Pages/EventShowPublic.vue
  - участие для подтвержденных пользователей, попап смены статуса с причиной,
    логика «резерв» и тексты статусов

## Дополнительно
- app/Domain/Events/Models/Event.php (связь participants)
- app/Models/User.php (связь eventParticipations)
