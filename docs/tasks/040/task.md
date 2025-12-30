# task_name
Адреса: доменная сущность и связь с площадками

# task_status
[100%]

# task_init_description
необходимо добавить доменную сущность Address, содержащую поля - string город, string street, string building, string nullable str_address. Чтобы у площадок адрес был не атрибутом площадки, а связь 1-to-many c моделью адрес

# task_short_description
Добавить Address и перевести площадки на связь 1-to-many вместо строкового адреса.

# task_full_description
Нужно создать доменную сущность Address с полями: city (string), street (string), building (string), str_address (nullable string). Для площадок адрес должен быть отдельной сущностью и храниться в связи 1-to-many. Необходимо обновить модель площадки, миграции, формы создания/редактирования, список площадок, модерацию площадок и отображение адресов.

# План выполнения задачи
1. Создать модель Address, миграции и связи с Venue, обновить логирование. (docs/tasks/040/subtasks/step-001.md)
2. Перенести адреса площадок в сущность Address и обновить бэкенд-валидацию. (docs/tasks/040/subtasks/step-002.md)
3. Обновить фронтенд: формы, списки и отображение адресов. (docs/tasks/040/subtasks/step-003.md)
