.PHONY: build up down restart logs composer-install shell status

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
