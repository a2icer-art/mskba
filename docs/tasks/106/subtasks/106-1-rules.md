# 106-1-rules

Цель: описать и зафиксировать правила по типам событий (игровые/неигровые), разрешенные роли и лимитируемую роль.

## Предложение источника правил
На текущем этапе — конфигурация в config/events.php, блок event_type_rules. Причины: быстрое внедрение, не требует админки/миграций, легко менять на уровне проекта. Позже можно перенести в таблицу event_types.

## Правила по типам событий (согласовано)
- game:
  - limit_role: player
  - allowed_roles: [player, coach, referee, media, seller, staff]
- game_training:
  - limit_role: player
  - allowed_roles: [player, coach, referee, media, seller, staff]
- training:
  - limit_role: player
  - allowed_roles: [player, coach, referee, media, seller, staff]

## Общие правила
- Лимит participants_limit применяется ко всем типам событий, но учитывает только limit_role (player).
- Любые роли, не входящие в allowed_roles, не допускаются к присоединению/приглашению.
- Для остальных ролей лимит не применяется.

## Категории ролей (экономика)
- Платные роли: [referee, coach, media, staff].
- Бесплатные роли = allowed_roles - paid_roles (например, player и seller по умолчанию бесплатные).
