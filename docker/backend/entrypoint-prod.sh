#!/bin/sh
set -e

# Generate the JWT signing keypair on first boot, into the mounted volume.
# Keys are NOT baked into the image — they must survive container recreation.
if [ ! -f config/jwt/private.pem ] || [ ! -f config/jwt/public.pem ]; then
    echo "→ Generating JWT keypair…"
    mkdir -p config/jwt
    php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction
fi

# Ensure the prod cache is present (no-op when baked at build time).
echo "→ Warming Symfony prod cache…"
php bin/console cache:warmup --env=prod --no-interaction || true

echo "→ Starting FrankenPHP (HTTP on :80)…"
exec frankenphp run --config /etc/caddy/Caddyfile
