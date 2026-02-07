FROM ghcr.io/roadrunner-server/roadrunner:2025 AS roadrunner

FROM php:8.4-cli-bookworm

RUN apt-get update && apt-get install -y \
        libpq-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install \
        sockets \
        zip \
        pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr

WORKDIR /app

EXPOSE 80

CMD ["rr", "serve", "-c", ".rr.yaml"]
