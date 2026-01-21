# 01_contract_types

## Решение
- Новый состав типов контрактов площадки: `creator`, `owner`, `supervisor`, `employee`.
- Исторические типы `manager` и `controller` мигрируются в `supervisor`.
- Лейблы обновлены: `Супервайзер` вместо `Менеджер/Контроллер`.

## Изменения
- Enum `ContractType` обновлен на новый набор типов.
- Миграция данных: `contracts.contract_type` для `manager/controller` -> `supervisor`.
- Документация архитектуры обновлена под новый список типов.

## Миграция
Миграция: `database/migrations/2026_01_20_210000_update_contract_types_to_supervisor.php`.
