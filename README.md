# PHP 8.4 + Symfony 8 + RoadRunner + Temporal

Демонстрационный проект: PHP 8.4 приложение на базе **Symfony 8** и **RoadRunner** (вместо nginx/php-fpm) с оркестрацией бизнес-процессов через Temporal. Интеграция Symfony с RoadRunner через **baldinof/roadrunner-bundle**. Всё запускается в Docker.

## Технологический стек

- **PHP 8.4** — с расширениями sockets, zip, pdo_pgsql, grpc
- **Symfony 8.0** — полноценный фреймворк (Kernel, DI Container, Router, Console)
- **baldinof/roadrunner-bundle** — интеграция Symfony с RoadRunner (worker loop, kernel reboot)
- **RoadRunner 2025** — высокопроизводительный Go-сервер, запускающий PHP как долгоживущий процесс
- **Temporal** — платформа оркестрации workflow с гарантией выполнения
- **PostgreSQL 16** — база данных (используется Temporal для хранения состояния)
- **Docker Compose** — оркестрация всех сервисов

## Архитектура

### Обработка HTTP-запросов

```
Клиент → RoadRunner (порт 80) → Symfony Kernel (public/index.php)
                ↓
        Статика из public/
        (.html, .css, .js, .ico, .txt)
```

RoadRunner держит PHP-процессы в памяти — они не пересоздаются на каждый запрос. Через `baldinof/roadrunner-bundle` Runtime бандл автоматически управляет worker loop, ребутом ядра Symfony и очисткой ресурсов между запросами.

### Temporal workflow

```
Клиент (src/client.php)
    ↓ gRPC
Temporal Server (порт 7233)
    ↓
Temporal Worker (src/temporal-worker.php)
    ↓
SayHelloWorkflow → GreetingActivity → "Hello, {name}!"
```

Temporal worker запускается как отдельный экземпляр RoadRunner со своим конфигом (`.rr-temporal.yaml`).

### Docker-сервисы

| Сервис | Образ | Порт | Назначение |
|--------|-------|------|------------|
| `php` | php:8.4-cli + Composer | — | Базовый образ для одноразовых команд (`composer install`) |
| `app` | php + RoadRunner | 80 | HTTP-приложение (Symfony + RoadRunner) |
| `temporal-worker` | php + RoadRunner | — | Worker для Temporal workflow и activity |
| `temporal` | temporalio/auto-setup | 7233 | Temporal Server |
| `temporal-ui` | temporalio/ui | 8233 | Веб-интерфейс Temporal |
| `postgres` | PostgreSQL 16 | 5432 | База данных (user/pass/db: `app`/`app`/`app`) |

## Структура проекта

```
.
├── bin/
│   └── console                   # Symfony Console entry point
├── config/
│   ├── bundles.php               # FrameworkBundle + BaldinofRoadRunnerBundle
│   ├── packages/
│   │   ├── framework.yaml        # Symfony Framework config
│   │   └── baldinof_road_runner.yaml  # RoadRunner bundle config (kernel reboot)
│   ├── routes.yaml               # Attribute routing из src/Controller/
│   └── services.yaml             # Autowiring с исключением Temporal-классов
├── docker/
│   ├── php/Dockerfile            # Базовый PHP 8.4 образ с расширениями
│   ├── rr/Dockerfile             # PHP + RoadRunner бинарник
│   └── postgres/
│       ├── Dockerfile
│       └── init.sql              # Инициализация БД
├── public/
│   ├── index.php                 # Symfony entry point (RoadRunner worker через Runtime)
│   ├── index.html                # Статические файлы (отдаются RoadRunner напрямую)
│   └── robots.txt
├── src/
│   ├── Kernel.php                # Symfony MicroKernel
│   ├── Controller/
│   │   └── HelloController.php   # HTTP-контроллер (маршрут "/")
│   ├── temporal-worker.php       # Temporal worker — регистрация workflow и activity
│   ├── client.php                # Клиент для запуска workflow
│   ├── Workflow/
│   │   └── SayHelloWorkflow.php
│   └── Activity/
│       └── GreetingActivity.php
├── .env                          # Переменные окружения (APP_ENV, APP_SECRET)
├── .rr.yaml                      # Конфиг RoadRunner для HTTP
├── .rr-temporal.yaml             # Конфиг RoadRunner для Temporal worker
├── docker-compose.yml
├── composer.json
└── Makefile
```

## Быстрый старт

```bash
# 1. Клонировать репозиторий
git clone <url> && cd symfony-road-runner-temporal

# 2. Собрать Docker-образы
make build

# 3. Установить PHP-зависимости
make composer-install

# 4. Запустить все сервисы
make up

# 5. Проверить работу HTTP-приложения
curl http://localhost
# Ожидаемый вывод: Hello from Symfony + RoadRunner! <timestamp>

# 6. Запустить пример Temporal workflow
make temporal-client
# Ожидаемый вывод: Result: Hello, Temporal!

# 7. Открыть Temporal UI
# http://localhost:8233
```

## Команды Makefile

| Команда | Описание |
|---------|----------|
| `make build` | Собрать все Docker-образы |
| `make composer-install` | Установить PHP-зависимости (запускается в контейнере `php`) |
| `make up` | Запустить все сервисы в фоновом режиме |
| `make down` | Остановить все сервисы |
| `make restart` | Перезапустить все сервисы (down + up) |
| `make logs` | Показать логи всех контейнеров (follow) |
| `make shell` | Открыть shell в контейнере `app` |
| `make status` | Показать статус контейнеров |
| `make temporal-client` | Запустить пример workflow SayHello |
| `make temporal-logs` | Показать логи Temporal worker (follow) |
| `make sf-console CMD=...` | Запустить Symfony Console команду (например `CMD=debug:router`) |
| `make cache-clear` | Очистить кеш Symfony |

## Как это работает

### RoadRunner HTTP worker

Файл `public/index.php` — точка входа для HTTP-обработки. Через `baldinof/roadrunner-bundle` Runtime бандл автоматически:

1. Создаёт Symfony Kernel
2. Запускает worker loop (приём запросов от RoadRunner)
3. Конвертирует PSR-7 запросы в Symfony HttpFoundation Request
4. Передаёт запрос через Symfony Kernel (routing → controller → response)
5. Конвертирует Symfony Response обратно в PSR-7 и отправляет RoadRunner
6. Управляет ребутом ядра (по исключениям, по лимиту задач)

Пул worker-ов настраивается в `.rr.yaml` (по умолчанию 2 воркера, максимум 64 задачи на воркер).

### Temporal workflow

**Workflow** (`SayHelloWorkflow`) — описывает бизнес-логику: создаёт activity stub и вызывает метод `greet()`.

**Activity** (`GreetingActivity`) — выполняет конкретное действие: формирует приветственную строку.

**Client** (`src/client.php`) — подключается к Temporal Server по gRPC и запускает workflow.

**Worker** (`src/temporal-worker.php`) — регистрирует классы workflow и activity, затем ожидает задачи от Temporal Server.

### Статические файлы

RoadRunner обслуживает статические файлы из `public/` напрямую, без участия PHP. Разрешённые расширения: `.html`, `.txt`, `.css`, `.js`, `.ico`.

## Конфигурация

### `.rr.yaml` — HTTP worker

- `server.command` — команда запуска Symfony (`php public/index.php`)
- `server.env.APP_RUNTIME` — класс Runtime для интеграции с RoadRunner (`Baldinof\RoadRunnerBundle\Runtime\Runtime`)
- `http.address` — адрес прослушивания (`0.0.0.0:80`)
- `http.static` — настройки раздачи статики из `public/`
- `http.pool` — размер пула воркеров и лимит задач
- `rpc.listen` — адрес RPC-сервера для управления RoadRunner

### `.rr-temporal.yaml` — Temporal worker

- `server.command` — команда запуска Temporal worker (`php src/temporal-worker.php`)
- `temporal.address` — адрес Temporal Server (`temporal:7233`)
- `temporal.activities.num_workers` — количество воркеров для activity (2)

### Symfony конфигурация

- `config/packages/framework.yaml` — секрет приложения, роутер, логирование PHP-ошибок
- `config/packages/baldinof_road_runner.yaml` — стратегия ребута ядра (`on_exception`, `max_jobs: 500`)
- `config/services.yaml` — автоматический autowiring с исключением Temporal-классов
- `.env` — переменные окружения (`APP_ENV`, `APP_DEBUG`, `APP_SECRET`)

## Важно

**Изменения в коде требуют перезапуска.** RoadRunner держит PHP-процессы в памяти — изменения файлов в `src/` не подхватываются автоматически. После правок нужно выполнить:

```bash
make restart
```
