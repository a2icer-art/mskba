# Архитектура

Документ описывает текущую доменную архитектуру, сущности и их связи.

См. также: [Процессы](processes.md).

## Общая структура

- Стек: Laravel (backend) + Inertia.js + Vue (frontend).
- Доменная логика организована в `app/Domain/<DomainName>`. Пути и неймспейсы соответствуют PSR-4.
 - Бизнес‑логика размещается в доменных модулях, контроллеры и роуты остаются тонкими.

## Домен Users

- `User` (app/Models/User)
  - Учетная запись пользователя.
  - Связи: profile (1:1), roles (M:N), userRoles (1:N).
  - Поля `name` и `email` удалены; имя хранится в `UserProfile`, email — в `UserContact`. (Задача: `docs/tasks/023`)
  - Статусы: unconfirmed/confirmed/blocked; блокировка хранит причину и метаданные (blocked_at/blocked_by/block_reason).
- `UserProfile` (app/Domain/Users/Models/UserProfile)
  - Дополнительные данные профиля (имя, дата рождения, пол).
- `UserContact` (app/Domain/Users/Models/UserContact)
  - Контакты пользователя (1:N) с типом (email/phone/telegram/vk/other) и подтверждением (confirmed_at).
  - Неподтвержденные контакты удаляются физически, подтвержденные — не удаляются.
- `ContactVerification` (app/Domain/Users/Models/ContactVerification)
  - Одноразовые коды подтверждения контактов (TTL, попытки, verified_at).
- `ContactDeliveryResolver` (app/Domain/Users/Infrastructure/ContactDeliveryResolver)
  - Роутер каналов доставки кода подтверждения по типу контакта.
- `ContactDelivery` (app/Domain/Users/Contracts/ContactDelivery)
  - Контракт отправки одноразового кода через конкретный канал.
- `Role` (app/Domain/Users/Models/Role)
  - Роли доступа (admin/moderator/editor).
- `UserRole` (app/Domain/Users/Models/UserRole)
  - Связь между User и Role, с аудитом и статусами.

## Домен Moderation

- `ModerationRequest` (app/Domain/Moderation/Models/ModerationRequest)
  - Универсальная заявка на модерацию сущностей (user/venue/event).
  - Хранит статус (pending/approved/rejected), отправителя, даты отправки и рассмотрения.
- `ModerationEntityType` / `ModerationStatus` enums
  - Тип подтверждаемой сущности и статус заявки.
- Правила модерации
  - Для каждой сущности есть набор требований (реализовано через контракт `ModerationRulesContract`).
  - Для пользователя проверяются: подтверждённый контакт, заполненные ФИО, пол и дата рождения.
- Use Case `SubmitModerationRequest`
  - Создаёт заявку при отсутствии активной (pending) и при выполнении требований.
  - Возвращает список невыполненных требований для UI.

## Домен Venues

- `Venue` (app/Domain/Venues/Models/Venue)
  - Место проведения (зал/площадка). Ссылка на тип VenueType.
  - Доступность: `is_available` (по умолчанию true), `closure_reason` (nullable).
- `VenueType` (app/Domain/Venues/Models/VenueType)
  - Справочник типов мест проведения.
- `VenueCatalogService` (app/Domain/Venues/Services/VenueCatalogService)
  - Сервис формирования навигации и списка площадок для витрины.
- `VenueStatus` enum

## Домен Participants

- `ParticipantRole` (app/Domain/Participants/Models/ParticipantRole)
  - Справочник ролей участников процесса.
- `ParticipantRoleProfileFactory` (app/Domain/Participants/Services/ParticipantRoleProfileFactory)
  - Фабрика создания профильной сущности по роли участника.
- `ParticipantRoleAssignment` (app/Domain/Participants/Models/ParticipantRoleAssignment)
  - Назначение роли пользователю с опциональным контекстом (polymorphic).

## Домен Filament

- `FilamentNavigationService` (app/Domain/Filament/Services/FilamentNavigationService)
  - Формирует меню разделов панели управления на основе уровня роли.

## Связи между доменами

- Пользователь может иметь роли доступа и роли участников процесса.
- Роли участников могут быть глобальными или контекстными через polymorphic context.

## Принципы и ограничения

- Контакты и подтверждения относятся к домену Users и не зависят от HTTP‑слоя.
- Подтвержденные контакты не редактируются и не удаляются.
- Генерация одноразовых кодов абстрагирована через контракт отправки.
- Если в слой представления/схемы передается `User`, то вычисляемые из него параметры
  (например, `roleLevel`, `permissions`) не должны рассчитываться в контроллере и не обязаны
  передаваться; при отсутствии они вычисляются внутри соответствующего слоя.
