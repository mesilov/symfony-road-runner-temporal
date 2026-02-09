# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Docker-based PHP 8.4 application using **Symfony 8** framework with **RoadRunner** as the application server (not nginx/php-fpm) and PostgreSQL. Integration via **baldinof/roadrunner-bundle** — RoadRunner runs PHP as a long-lived worker process with Symfony Kernel handling HTTP requests.

## Build & Run Commands

```bash
make build              # Build all Docker images
make composer-install   # Install PHP dependencies (runs in php container)
make up                 # Start all services (detached, dev mode)
make up-prod            # Start all services (prod mode, with resource limits)
make down               # Stop all services
make restart            # Stop + start
make logs               # Tail logs from all containers
make shell              # Shell into the running app container
make status             # Show container status
make temporal-client    # Run example Temporal workflow (SayHello)
make temporal-logs      # Tail logs from temporal-worker container
make sf-console CMD=... # Run Symfony Console command (e.g. CMD=debug:router)
make cache-clear        # Clear Symfony cache
make cache-warmup       # Warm up Symfony cache (prod)
make test               # Run PHPUnit tests
make phpstan            # Run PHPStan static analysis (level 8)
```

## Environments

- **Dev** (`make up`): bind-mount volumes, `APP_DEBUG=1`, all ports exposed, debug logging to stderr, Buggregator debug server on port 8000
- **Prod** (`make up-prod`): no volumes, `APP_ENV=prod`, `APP_DEBUG=0`, JSON logging to stderr, resource limits, `restart: unless-stopped`

Prod uses compose override: `docker-compose.yml` + `docker-compose.prod.yml`.

Environment variables are configured via `.env` / `.env.local`. See `.env.example` for the template.

## Architecture

**Request flow:** Client → RoadRunner (Go binary, port 80) → Symfony Kernel (`public/index.php`) via baldinof/roadrunner-bundle Runtime

- RoadRunner serves static files from `public/` directly (configured in `.rr.yaml` with static middleware)
- All other requests are dispatched to the Symfony Kernel through the baldinof/roadrunner-bundle worker loop
- The bundle handles PSR-7 ↔ HttpFoundation conversion, kernel reboot strategy, and resource cleanup between requests
- Controllers in `src/Controller/` use Symfony attribute routing
- Symfony DI Container provides autowiring for services
- Health check endpoint: `GET /health` → `{"status":"ok"}`
- Prometheus metrics: RoadRunner exports on port 2112

**Temporal workflow:** Client (`src/client.php`) → Temporal server (port 7233) → Temporal worker (`src/temporal-worker.php`) → Workflow/Activity classes

- Temporal worker runs as a separate RoadRunner instance with its own config (`.rr-temporal.yaml`)
- Example workflow: `SayHelloWorkflow` calls `GreetingActivity` to return a greeting
- Temporal UI available at http://localhost:8233

**Docker services (docker-compose.yml):**
- `php` — Base PHP 8.4 CLI image with extensions (sockets, zip, pdo_pgsql, grpc) + Composer. Used for running one-off commands like `composer install`.
- `app` — RoadRunner image (extends php base + RoadRunner binary). The running Symfony HTTP application on port 80. Has Docker HEALTHCHECK via `/health`.
- `temporal-worker` — RoadRunner image running the Temporal worker (`src/temporal-worker.php`) with `.rr-temporal.yaml` config.
- `temporal` — Temporal server (auto-setup image) on port 7233, uses PostgreSQL as its backing store. Has Docker HEALTHCHECK.
- `temporal-ui` — Temporal Web UI on port 8233.
- `postgres` — PostgreSQL 16 on port 5432. Has Docker HEALTHCHECK via `pg_isready`. Credentials via env vars (defaults: `app`/`app`/`app`).
- `buggregator` — Buggregator debug server (dev profile only). Web UI on port 8000. Receives Monolog logs (port 9913), VarDumper dumps (port 9912), SMTP (port 1025).

**Key config:**
- `.rr.yaml` — RoadRunner HTTP worker config (server command, HTTP address, static files, worker pool with supervisor, JSON logging, Prometheus metrics on 2112, RPC).
- `.rr-temporal.yaml` — RoadRunner Temporal worker config (Temporal server address, activity worker pool).
- `config/packages/framework.yaml` — Symfony framework config (secret, router, php_errors).
- `config/packages/baldinof_road_runner.yaml` — Kernel reboot strategy (on_exception, max_jobs).
- `config/packages/monolog.yaml` — Logging channels. Dev: debug to stderr. Prod: JSON to stderr.
- `config/services.yaml` — Autowiring config (excludes Temporal classes and standalone scripts).

## Testing & Quality

- **PHPUnit**: `make test` — runs tests in `tests/` directory. Config in `phpunit.xml.dist`.
- **PHPStan**: `make phpstan` — static analysis at level 8. Config in `phpstan.neon`.
- Smoke test: `tests/Controller/HealthControllerTest.php` covers the `/health` endpoint.

## Key Constraint

Because RoadRunner keeps PHP workers alive, any code changes to `src/` or `config/` require restarting the app container (`make restart`) — there is no automatic reload.
