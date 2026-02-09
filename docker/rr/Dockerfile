FROM ghcr.io/roadrunner-server/roadrunner:2025.1.6 AS roadrunner

FROM php:8.4-cli-bookworm

RUN apt-get update && apt-get install -y \
        libpq-dev \
        libzip-dev \
        unzip \
        curl \
    && docker-php-ext-install \
        sockets \
        zip \
        pdo_pgsql \
    && pecl install grpc \
    && docker-php-ext-enable grpc \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr

RUN addgroup --gid 1000 appuser && adduser --uid 1000 --gid 1000 --disabled-password --gecos "" appuser
USER appuser

WORKDIR /app

EXPOSE 80

CMD ["rr", "serve", "-c", ".rr.yaml"]
