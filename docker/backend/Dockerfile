# syntax=docker/dockerfile:1

# ---------- Base (dev + composer build) ----------
FROM php:8.3-cli-alpine AS base

RUN apk add --no-cache \
        git unzip icu-dev postgresql-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" intl pdo pdo_pgsql zip opcache \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# ---------- Dev ----------
FROM base AS dev

ENV APP_ENV=dev
# The source tree is bind-mounted in dev; deps live in a named volume.
COPY docker/backend/entrypoint-dev.sh /usr/local/bin/entrypoint-dev.sh
RUN chmod +x /usr/local/bin/entrypoint-dev.sh
CMD ["/usr/local/bin/entrypoint-dev.sh"]

# ---------- Build (prod deps, optimized autoloader, warm cache) ----------
FROM base AS build

ENV APP_ENV=prod
COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist
COPY backend/ ./
RUN composer install --no-dev --no-interaction --optimize-autoloader \
    && php bin/console cache:clear --env=prod --no-warmup \
    && php bin/console cache:warmup --env=prod

# ---------- Prod (FrankenPHP) ----------
# Traefik terminates TLS in front; this container serves plain HTTP on :80.
FROM dunglas/frankenphp:1-php8.3 AS prod

# Production PHP runtime extensions (image ships `install-php-extensions`).
RUN install-php-extensions pdo_pgsql intl redis zip opcache

# Optimized PHP settings for production.
COPY docker/backend/php.prod.ini $PHP_INI_DIR/conf.d/zz-copot.prod.ini

WORKDIR /app

ENV APP_ENV=prod \
    APP_DEBUG=0

# Application code + warmed prod cache.
COPY --from=build /app /app

# Caddy/FrankenPHP config (HTTP only — no TLS here).
COPY docker/backend/Caddyfile /etc/caddy/Caddyfile

# Generate JWT keys on first boot (volume-mounted /app/config/jwt), then serve.
COPY docker/backend/entrypoint-prod.sh /usr/local/bin/entrypoint-prod.sh
RUN chmod +x /usr/local/bin/entrypoint-prod.sh

EXPOSE 80
CMD ["/usr/local/bin/entrypoint-prod.sh"]
