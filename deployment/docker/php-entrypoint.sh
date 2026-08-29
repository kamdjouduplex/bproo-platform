#!/usr/bin/env sh
set -e

cd /var/www/html

# Empty named volumes need Laravel dirs on first boot
mkdir -p \
  storage/app/public \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/framework/testing \
  storage/logs \
  bootstrap/cache

if [ -d "storage" ] && [ -d "bootstrap/cache" ]; then
  chown -R www-data:www-data storage bootstrap/cache || true
  chmod -R ug+rwx storage bootstrap/cache || true
fi

# Named volume bootstrap/cache can keep a packages.php from a --dev install
# (e.g. Collision). Production images use composer --no-dev — rediscover.
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
if [ -f artisan ]; then
  php artisan package:discover --ansi --no-interaction 2>/dev/null || true
fi

if [ ! -e "public/storage" ] && [ -d "storage/app/public" ]; then
  php artisan storage:link 2>/dev/null || ln -sfn ../storage/app/public public/storage
fi

if [ -n "${APP_KEY}" ]; then
  # config:cache is safe. route:cache / view:cache break Livewire full-page
  # components (login works, dashboard 500). Named volume bootstrap/cache can
  # keep a stale routes-v7.php across rebuilds — always drop it on boot.
  php artisan route:clear >/dev/null 2>&1 || true
  php artisan view:clear >/dev/null 2>&1 || true
  php artisan config:cache || true
fi

exec "$@"
