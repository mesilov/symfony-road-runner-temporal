# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Docker-based PHP 8.4 application using RoadRunner as the application server (not nginx/php-fpm) and PostgreSQL. RoadRunner runs PHP as a long-lived worker process — PHP scripts stay in memory between requests.

## Build & Run Commands

```bash
make build              # Build all Docker images
make composer-install   # Install PHP dependencies (runs in php container)
make up                 # Start all services (detached)
make down               # Stop all services
make restart            # Stop + start
make logs               # Tail logs from all containers
make shell              # Shell into the running app container
make status             # Show container status
make temporal-client    # Run example Temporal workflow (SayHello)
make temporal-logs      # Tail logs from temporal-worker container
```

## Architecture

**Request flow:** Client → RoadRunner (Go binary, port 80) → PHP worker (`src/worker.php`)

- RoadRunner serves static files from `public/` directly (configured in `.rr.yaml` with static middleware)
- All other requests are dispatched to the PHP worker process
- The worker runs a persistent loop via `Spiral\RoadRunner\Http\PSR7Worker`, receiving PSR-7 requests and sending PSR-7 responses
- Worker delegates to `src/test.php` which returns the response body

**Temporal workflow:** Client (`src/client.php`) → Temporal server (port 7233) → Temporal worker (`src/temporal-worker.php`) → Workflow/Activity classes

- Temporal worker runs as a separate RoadRunner instance with its own config (`.rr-temporal.yaml`)
- Example workflow: `SayHelloWorkflow` calls `GreetingActivity` to return a greeting
- Temporal UI available at http://localhost:8233

**Docker services (docker-compose.yml):**
- `php` — Base PHP 8.4 CLI image with extensions (sockets, zip, pdo_pgsql, grpc) + Composer. Used for running one-off commands like `composer install`.
- `app` — RoadRunner image (extends php base + RoadRunner binary). The running HTTP application on port 80.
- `temporal-worker` — RoadRunner image running the Temporal worker (`src/temporal-worker.php`) with `.rr-temporal.yaml` config.
- `temporal` — Temporal server (auto-setup image) on port 7233, uses PostgreSQL as its backing store.
- `temporal-ui` — Temporal Web UI on port 8233.
- `postgres` — PostgreSQL 16 on port 5432 (user/pass/db: `app`/`app`/`app`)

**Key config:**
- `.rr.yaml` — RoadRunner HTTP worker config (worker command, HTTP address, static file serving, worker pool size, RPC).
- `.rr-temporal.yaml` — RoadRunner Temporal worker config (Temporal server address, activity worker pool).

## Key Constraint

Because RoadRunner keeps PHP workers alive, any code changes to `src/` require restarting the app container (`make restart`) — there is no automatic reload.
