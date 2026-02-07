.PHONY: build up down restart logs composer-install shell status temporal-client temporal-logs sf-console cache-clear

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose down
	docker compose up -d

logs:
	docker compose logs -f

composer-install:
	docker compose run --rm php composer install

shell:
	docker compose exec app sh

status:
	docker compose ps

temporal-client:
	docker compose run --rm php php src/client.php

temporal-logs:
	docker compose logs -f temporal-worker

sf-console:
	docker compose exec app php bin/console $(CMD)

cache-clear:
	docker compose exec app php bin/console cache:clear
