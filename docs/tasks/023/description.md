# task_init_description
Вероятно из модели User можно убрать поле name, так как оно перенесено в UserProfile.

# task_short_description
Проверить возможность удаления поля name из модели User, а также переноса email в модель UserEmail.

# task_full_description
Проверить использование полей `name` и `email` в модели User и в UI/логике. Перенести email в модель UserEmail (1:N) с подтверждением (confirmed_at). Удалить `name` и `email` из users, обновить регистрацию, отображение аккаунта и связанные процессы (миграции, сидеры, фабрики). Зафиксировать итог в документации.
