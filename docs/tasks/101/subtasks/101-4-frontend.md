# Подзадача 101-4: frontend

Цель: UI на странице /account (вкладка/секция "Настройки").

Что сделать:
- добавить блок уведомлений
- чекбоксы типов и каналов
- отображение контактов и статуса подтверждения

Выполнено:
- Страница `resources/js/Pages/AccountNotificationsSettings.vue` с UI списка уведомлений и каналов (с чекбоксами по каждому подтвержденному контакту).
- Добавлен пункт меню "Уведомления" в `app/Presentation/Navigation/AccountNavigationPresenter.php`.
- Добавлен fallback пункт меню в `resources/js/Pages/Account.vue`.

Поток:
- GET `/account/settings/notifications` → данные и UI.
- PATCH `/account/settings/notifications` → сохранение.
