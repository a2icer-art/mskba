# MSKBA — Deploy Guide

> Описание того, как устроена синхронизация репозитория и серверов,
> какие ветки в какие окружения деплоятся и как вносить изменения.

> **Примечание:** конкретные серверные пути, имена systemd-юнитов,
> имена GitHub Secrets и прочая инфраструктурная специфика хранятся
> в приватном `myconfigs/deploy.md` (не коммитится в репозиторий).

---

## 1. Архитектура деплоя

### Ветки и окружения
- `dev` → dev-окружение: https://dev.mskba.ru
- `main` → prod-окружение: https://mskba.ru

### Поток деплоя
1. GitHub Actions workflow — `.github/workflows/deploy.yml`:
   - **Build frontend** — собирает Vite (`npm ci` + `npm run build`), публикует `public/build` как artifact.
   - **Deploy to dev** — триггерится на push в `dev`. Качает artifact, копирует на сервер, запускает deploy-скрипт dev, распаковывает `public/build`.
   - **Deploy to prod** — триггерится на push в `main`, требует manual approval через GitHub Environment `production`, затем деплоит prod.
2. Серверные deploy-скрипты (запускаются Actions по SSH):
   - `git fetch` + `git reset --hard origin/<branch>`
   - `composer install`
   - `php artisan migrate --force`
   - `php artisan optimize:clear`
   - Корректировка прав/группы на `storage`, `bootstrap/cache`
   - Vite build **не выполняется на сервере** — приходит готовым artifact'ом из Actions.

---

## 2. Production protection

- Для prod настроен GitHub Environment `production` с правилом **Required reviewers**.
- Любой push в `main` создаёт deploy, который переходит в статус **Waiting** до manual approve.

---

## 3. Рабочий процесс разработчика

### Локальная разработка
```
composer run dev
```
Запускает параллельно `php artisan serve`, `queue:listen` и `npm run dev` (Vite с hot-reload).

> Локальный hot-reload ≠ то, что будет на сервере: на сервер попадает результат `npm run build`, который делает GitHub Actions.

### Отправка на dev
1. Коммит и `git push origin dev`
2. GitHub → Actions → workflow **Build and Deploy**
3. Убедиться, что прошли: **Build frontend** ✅, **Deploy to dev** ✅
4. Проверить https://dev.mskba.ru

> `npm run build` локально для dev-деплоя делать **не нужно** — сборка идёт в Actions.

### Релиз на prod
1. Изменения должны быть проверены на dev.
2. Merge `dev` → `main` (через PR или прямой merge).
3. Push/merge в `main` запускает workflow, который попросит **Approve pending deployments** для environment `production`.
4. После approve выполняется Deploy to prod.
5. Проверить https://mskba.ru

---

## 4. Стратегия веток

### Основное правило
- `main` — только для релизов (production-ready).
- Разработка ведётся через `dev`.

### Рекомендуемая схема
- Feature-ветки ответвляются от `dev`:
  - `feature/<ticket-or-short-name>`
  - `fix/<ticket-or-short-name>`
- После завершения — PR в `dev`, тест на dev-окружении.
- Релиз — PR/merge `dev` → `main` (prod-деплой требует approve).

### Почему так
- `main` всегда остаётся стабильной.
- Dev-окружение — площадка для проверки интеграции.
- Prod защищён manual approve, не обновится случайно.

---

## 5. Секреты

В GitHub → Settings → Secrets and variables → Actions настроены секреты для SSH-доступа к серверу и отдельных интеграций. Конкретные имена и значения — в `myconfigs/deploy.md`.

---

## 6. Частые проблемы и быстрые проверки

### 6.1. SMTP / email-доставка (dev vs prod)
- В **dev** допустимо временно использовать SMTP без шифрования (порт 25, None).
- В **prod** обязательно TLS/SSL (587/465) с валидным сертификатом, соответствующим SMTP-хосту.

### 6.2. Actions использует "старый" workflow
- GitHub Actions выполняет workflow из того коммита, который запустил run.
- После правки `deploy.yml` нужен **новый push**, чтобы запустился новый run.
- **Re-run jobs запускает старую версию workflow** (из того же коммита).

### 6.3. 500 на dev
Почти всегда — `storage` / `bootstrap/cache` не writable:
- Проверить права (должен писать `www-data`).
- Смотреть nginx и laravel logs.

### 6.4. "sudo: a password is required" в Actions
- Значит, какая-то команда в deploy-цепочке вызвала `sudo` без `-n`.
- Решение: либо использовать `sudo -n` везде, либо настроить sudoers для нужных команд без пароля.

### 6.5. SSH / host verification
- "Host key verification failed" — отсутствуют нужные ключи в `known_hosts` или включена строгая проверка.

---

## 7. Работа с .env на сервере

> `.env` хранится **только на сервере**, не коммитится в репо.

После правки `.env`:
1. Очистить кеши: `php artisan optimize:clear`
2. Перезапустить Reverb (systemd-юнит, имя см. `myconfigs/deploy.md`).

---

## 8. Seeders и factories на dev

На dev запускать можно, но с осторожностью — меняется БД:
- Убедиться, что `.env` указывает на dev-базу.
- Оценить, не затрёт ли это ручные тестовые данные.

Типовой запуск:
```
php artisan db:seed
php artisan db:seed --class=SomeSeeder
```

Factories — через tinker или сидеры.

---

## 9. WebSocket / Reverb

Reverb — WebSocket-сервер Laravel; держит соединение для realtime-обновлений (сообщения, статусы, счётчики).

### В норме
Reverb работает постоянно как systemd-юнит. Отключать имеет смысл только для краткой отладки или при изменениях `.env`, требующих перезапуска.

### После изменений `.env`
1. `php artisan optimize:clear`
2. Перезапуск Reverb systemd-юнита (имя — в `myconfigs/deploy.md`).

### Типовые причины "не работает"
- В `.env` не совпадают `REVERB_*` и `VITE_REVERB_*`.
- Фронт не пересобран после изменения `VITE_REVERB_*` (build должен пройти в Actions).
- Nginx не проксирует `/app` на Reverb.

---

## 10. Ожидаемое поведение после push

- **Push в `dev`:**
  - Автоматически собрать фронт → задеплоить dev → обновить `public/build`.
- **Push в `main`:**
  - Потребовать approve (environment `production`).
  - После approve — задеплоить prod.

---

> Детали путей, имён юнитов, имён секретов и прочая инфраструктурная специфика — в приватном `myconfigs/deploy.md` (не в репозитории).
