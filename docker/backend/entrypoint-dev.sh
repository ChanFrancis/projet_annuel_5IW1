#!/bin/sh
set -e

echo "→ Configuring Composer advisory policy…"
# All current Symfony 7.x patch releases are flagged by pending advisories with no
# published fix yet, which blocks resolution. We relax the *blocking* so the dev
# stack boots, but keep `composer audit` available to review issues explicitly.
composer config policy.advisories.block false 2>/dev/null || true

echo "→ Installing PHP dependencies…"
composer install --no-interaction

echo "→ Ensuring JWT keypair…"
if [ ! -f config/jwt/private.pem ]; then
    php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction
fi

echo "→ Waiting for database…"
until php -r 'exit(@fsockopen("postgres", 5432) ? 0 : 1);' 2>/dev/null; do
    sleep 1
done

echo "→ Syncing database schema…"
php bin/console doctrine:database:create --if-not-exists --no-interaction || true
# Dev convenience: sync schema straight from entities. Prod uses real migrations.
php bin/console doctrine:schema:update --force --complete --no-interaction || true

echo "→ Loading fixtures (dev only)…"
php bin/console doctrine:fixtures:load --no-interaction || true

echo "→ Starting PHP dev server on :8000"
exec php -S 0.0.0.0:8000 -t public
