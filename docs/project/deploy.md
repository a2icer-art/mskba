# MSKBA — Repo ↔ Server Sync / Deploy Guide (for Claude Code AI Agent)

> Цель: этот документ описывает, как сейчас устроена синхронизация репозитория и серверов, какие ветки и куда деплоятся, что уже сделано, и как правильно вносить изменения дальше.

---

## 1) Архитектура деплоя: что куда попадает

### Ветки и окружения
- `dev` → dev-окружение: сайт https://dev.mskba.ru, код на сервере в /var/www/mskba-dev
- `main` → prod-окружение: сайт https://mskba.ru, код на сервере в /var/www/mskba-prod

### Где живёт деплой-логика
1) GitHub Actions workflow:
   - файл: .github/workflows/deploy.yml
   - три ключевых job’а:
     - Build frontend: собирает Vite (`npm ci` + npm run build`), запаковывает `public/build в public-build.tgz и публикует как artifact
     - Deploy to dev: триггерится на push в dev, качает artifact, копирует его на сервер, запускает деплой-скрипт dev, распаковывает public/build
     - Deploy to prod: триггерится на push в main, требует manual approval через GitHub Environment production, затем делает то же самое, что dev, но для prod

2) Серверные deploy-скрипты (запускаются GitHub Actions по SSH):
   - /usr/local/bin/deploy-mskba-dev
   - /usr/local/bin/deploy-mskba-prod

Эти скрипты:
- переходят в каталог проекта (`/var/www/mskba-dev` или `/var/www/mskba-prod`)
- git fetch origin + git reset --hard origin/<branch>
- composer install ...
- php artisan migrate --force (на dev и prod)
- php artisan optimize:clear
- Vite build НЕ выполняется на сервере, он приходит готовым artifact’ом из Actions
- права/группы на нужные директории корректируются (важно для storage, `bootstrap/cache`)

---

## 2) Что уже исправлено/настроено (важно знать)

### 2.1. Права на storage/bootstrap/cache
- На dev ранее была ошибка 500 из-за невозможности писать в storage/logs/laravel.log
- Исправлено через корректные права/владельца и проверку writable
- На текущий момент dev и prod возвращают HTTP 200 и деплой проходит

### 2.2. Production protection (approve)
- Для prod создан GitHub Environment: `production`
- Для него настроено правило Required reviewers (сам себе reviewer допустим)
- Поэтому любой push в main создаёт deploy, который переходит в статус Waiting до manual approve

### 2.3. SSH host verification / known_hosts
- При запуске деплоя через Actions возникала ошибка Host key verification failed
- На сервере для пользователя deploy добавлен github.com в ~/.ssh/known_hosts
- Проверка sudo -u deploy ssh -T git@github.com проходит успешно

### 2.4. Sudo без пароля для deploy-юзера
- GitHub Actions выполняет команды non-interactive, поэтому sudo должен работать без запроса пароля
- Исправлено: deploy-скрипты и/или команды Actions используют sudo -n ...
- В результате прод-деплой перестал падать с sudo: a password is required

---

## 3) Правильный рабочий процесс разработчика (как вносить изменения)

### Локальная разработка
- Локально можно работать с hot-reload через:
  - composer run dev (или аналогичная команда), чтобы видеть изменения без сборки
- Важно: локальный hot-reload ≠ то, что будет на сервере.
  На сервер попадает только результат `npm run build`, который делает GitHub Actions.

### Как отправить изменения на dev.mskba.ru
1) Убедиться, что изменения в коде готовы
2) Создать коммит
3) git push origin dev
4) Открыть GitHub → Actions → workflow Build and Deploy
5) Убедиться, что прошли:
   - Build frontend ✅
   - Deploy to dev ✅
6) Проверить https://dev.mskba.ru

> НЕ нужно делать npm run build локально для dev-деплоя — сборка делается в Actions.

### Как отправить изменения на prod (mskba.ru)
1) Сначала изменения должны попасть в dev и быть проверены там
2) Затем выпускаем релиз:
- мерджим dev → main (через PR или merge)
3) Push/merge в main запускает workflow
4) GitHub попросит Approve pending deployments для environment production
5) После approve выполняется Deploy to prod
6) Проверить https://mskba.ru

---

## 4) Рекомендации по стратегии веток (чтобы не сломать прод)

### Основное правило
- `main` — только для релизов (production-ready)
- Разработка ведётся через `dev`

### Рекомендуемая схема
- Feature-ветки создавать только от `dev`:
  - feature/<ticket-or-short-name>
  - fix/<ticket-or-short-name>
- После завершения задачи:
  - PR → dev
  - тест на dev окружении
- Релиз:
  - PR/merge dev → main
  - prod deploy требует approve

### Почему так лучше
- main всегда остаётся стабильной веткой
- dev окружение — место для проверки интеграции изменений
- prod защищён manual approve и не обновится “случайно”

---

## 5) Важные места и “точки правды”

### На GitHub
- Actions: https://github.com/a2icer-art/mskba/actions
- Workflow: .github/workflows/deploy.yml
- Secrets (в Settings → Secrets and variables → Actions):
  - VDS_HOST
  - VDS_USER
  - VDS_SSH_KEY
  - VDS_PORT
- Environment:
  - production с required reviewers

### На сервере
- Dev проект: /var/www/mskba-dev
- Prod проект: /var/www/mskba-prod
- Скрипты:
  - /usr/local/bin/deploy-mskba-dev
  - /usr/local/bin/deploy-mskba-prod
- Frontend build лежит в:
  - /var/www/mskba-*/public/build

---

## 6) Частые проблемы и быстрые проверки

### 6.1. “Actions использует старый workflow”
- GitHub Actions выполняет workflow из того коммита, который запустил run
- Если вы поправили .github/workflows/deploy.yml, нужно:
  - чтобы правка реально попала в нужную ветку (dev или main)
  - сделать новый push/commit, чтобы запустился новый run
- Re-run jobs запускает старую версию workflow (из того же коммита)

### 6.2. 500 на dev
Почти всегда это:
- storage / bootstrap/cache не writable
- Проверка на сервере:
  - sudo -u www-data test -w /var/www/mskba-dev/storage && echo OK || echo FAIL
  - смотреть nginx и laravel logs

### 6.3. “sudo: a password is required” в Actions
- Значит, какая-то команда в deploy-цепочке вызвала sudo без -n
- Нужно:
  - либо использовать sudo -n везде в workflow/скриптах
  - либо настроить sudoers для нужных команд без пароля

### 6.4. SSH / host verification
- Если “Host key verification failed”:
  - значит нет нужных ключей в known_hosts или включена строгая проверка
  - корректный путь — добавить ключи github.com в ~deploy/.ssh/known_hosts

---

## 7) Работа с .env на dev (как безопасно редактировать)

> .env хранится только на сервере (не коммитится в репозиторий).

Открыть и добавить строку:
- sudo nano /var/www/mskba-dev/.env
- добавить нужную строку
- затем выполнить очистку кешей:
  - cd /var/www/mskba-dev && php artisan optimize:clear

(аналогично для prod, но изменения в prod .env делать крайне аккуратно)

---

## 8) Seeders и factories на dev (можно ли и как)

Да, на dev запускать можно, но важно понимать:
- сидеры/фабрики меняют данные в БД
- перед запуском желательно:
  - убедиться, что это dev база
  - понимать, не затрёт ли это ручные тестовые данные

Типовой запуск:
- cd /var/www/mskba-dev
- php artisan db:seed  
или конкретный сидер:
- php artisan db:seed --class=SomeSeeder

Factories обычно используют через tinker или сидеры.

---

## 9) Ключевое правило для агента

При любых изменениях, связанных с деплоем:
1) Считать источником истины .github/workflows/deploy.yml
2) Учитывать, что dev и prod — разные директории на сервере
3) Любые изменения деплоя сначала тестировать через push в dev
4) В main попадать только после подтверждения работоспособности на dev

---

## 10) Ожидаемое поведение после каждого пуша

- Push в dev:
  - должен автоматически собрать фронт, задеплоить dev, и обновить public/build
- Push в main:
  - должен потребовать approve (environment `production`)
  - после approve — задеплоить prod

---
