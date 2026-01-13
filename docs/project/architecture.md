# Архитектура

Документ описывает текущую доменную архитектуру, сущности и их связи.

См. также: [Процессы](processes.md).

## Общая структура

- Стек: Laravel (backend) + Inertia.js + Vue (frontend).
- Доменная логика организована в `app/Domain/<DomainName>`. Пути и неймспейсы соответствуют PSR-4.
 - Бизнес‑логика размещается в доменных модулях, контроллеры и роуты остаются тонкими.

## Presentation слой

- Презентационный слой находится в `app/Presentation` и отвечает за подготовку данных для Inertia/Vue.
- Базовый контракт: `BasePresenter::present(array $ctx): array`, результат — `['title' => string, 'data' => array]`.
- Навигации наследуются от `NavigationPresenter`, формат данных: массив `items` или `groups`.
- Контроллеры получают данные из презентеров и передают их в `Inertia::render(...)` без ручной переклейки.

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

## Домен Permissions

- `Permission` (app/Domain/Permissions/Models/Permission)
  - Реестр прав (code/label/scope/target_model).
- `RolePermission` / `UserPermission`
  - Назначения прав ролям и пользователям.
- `PermissionRegistry` / `RolePermissionPreset`
  - Единый реестр прав и базовые пресеты для ролей.
- `PermissionChecker`
  - Доменная проверка прав (global и resource).
- Интеграция с Laravel
  - Policy для ресурсных прав (например, `VenuePolicy`).
  - Gate для глобальных прав (`admin.access`, `moderation.access`, `logs.view`).
  - Доступы в UI и маршрутах должны опираться на permissions (например, `can:venue.create`).
  - Отправка площадки на модерацию контролируется ресурсным правом `venue.submit_for_moderation`.

## Домен Contracts

- `Contract` (app/Domain/Contracts/Models/Contract)
  - Контракт пользователя на конкретную сущность (`entity_type` + `entity_id`).
  - Поля: name, contract_type (creator/owner/manager/controller/employee), starts_at, ends_at, status (active/inactive), comment.
  - Связи: user (1:N), permissions (M:N через contract_permissions).
- Контрактные права используются для действий над конкретными сущностями.
  Глобальные права остаются в `roles` и `user_permissions`.
- Видимость площадок для пользователя учитывает активные контракты на эти площадки (кроме подтвержденных и созданных им).

## Домен Admin

- `AdminNavigationService` (app/Domain/Admin/Services/AdminNavigationService)
  - Формирует меню разделов панели управления на основе уровня роли.

## Сидеры (локальное тестирование)

- `MariqSeeder` (database/seeders/MariqSeeder.php)
  - Создает пользователя `mariq` для тестирования модерации.
  - Заполняет профиль и подтверждает email `mariq@mskba.ru`.
  - Назначает роль участника `player` (ParticipantRoleAssignment + PlayerProfile).

## Связи между доменами

- Пользователь может иметь роли доступа и роли участников процесса.
- Роли участников могут быть глобальными или контекстными через polymorphic context.

## Принципы и ограничения

- Контакты и подтверждения относятся к домену Users и не зависят от HTTP‑слоя.
- Подтвержденные контакты не редактируются и не удаляются.
- Генерация одноразовых кодов абстрагирована через контракт отправки.
- Глобальный обработчик исключений не должен перехватывать `ValidationException`, чтобы ошибки валидации
  корректно попадали в Inertia и отображались в формах.
- Для форм с автоподбором адреса: при очистке строки адреса нужно сбрасывать распарсенные скрытые поля,
  иначе логика доступности кнопок и валидации работает некорректно.
- Если в слой представления/схемы передается `User`, то вычисляемые из него параметры
  (например, `roleLevel`, `permissions`) не должны рассчитываться в контроллере и не обязаны
  передаваться; при отсутствии они вычисляются внутри соответствующего слоя.
## Контракты: иерархия и делегирование

- Иерархия типов (сверху вниз): admin > creator > owner > manager > controller/employee (один уровень).
- Тип контракта можно назначать только уровнем ниже.
- Активный owner может быть только один на сущность.
- Назначать/аннулировать контракты могут только admin/creator/owner/manager (manager — только ниже уровнем).
- Аннулировать можно только контракты, созданные самим назначателем (исключение: admin).
- Права, назначаемые в контракте, фильтруются по правам назначателя.
- Права contract.assign/contract.revoke разрешено назначать только при выдаче контрактов owner/manager и только admin/creator/owner.
- Контракт хранит created_by для контроля делегирования и аннулирования.
