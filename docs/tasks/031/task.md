# task_name
Площадки: VenueCatalogService

# task_status
[x]

# task_init_description
Вынести логику формирования навигации и списка площадок из routes/web.php в промежуточный слой VenueCatalogService с методами getNavigationItems и getHallsList.

# task_short_description
Добавить VenueCatalogService для навигации и списка площадок.

# full_description
Нужно вынести сбор навигации по типам площадок и построение списка площадок из routes/web.php в доменный сервис Venues. Сервис VenueCatalogService должен предоставлять методы getNavigationItems() и getHallsList(), а маршруты должны стать тонкими и использовать сервис. Логика подстановки plural_name и slug-типов остается прежней.

# План выполнения задачи
1. Создать VenueCatalogService в домене Venues с методами getNavigationItems() и getHallsList(). (см. `docs/tasks/031/subtasks/step-001.md`)
2. Обновить routes/web.php и использовать сервис вместо встроенной логики. (см. `docs/tasks/031/subtasks/step-002.md`)
3. Проверить работу /venues и /venues/{type}. (см. `docs/tasks/031/subtasks/step-003.md`)
