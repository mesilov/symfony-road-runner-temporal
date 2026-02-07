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

WORKDIR /app
