# syntax=docker/dockerfile:1

# ---------- Base ----------
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

# ---------- Build (prod deps + warm cache) ----------
FROM base AS build

ENV APP_ENV=prod
COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist || true
COPY backend/ ./
RUN composer install --no-dev --no-interaction --optimize-autoloader || true

# ---------- Prod ----------
FROM base AS prod

ENV APP_ENV=prod
COPY --from=build /app /app
EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
