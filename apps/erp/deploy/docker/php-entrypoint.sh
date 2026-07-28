#!/usr/bin/env sh
set -e

cd /var/www/html

if [ -d "storage" ] && [ -d "bootstrap/cache" ]; then
  chown -R www-data:www-data storage bootstrap/cache || true
  chmod -R ug+rwx storage bootstrap/cache || true
fi

if [ ! -e "public/storage" ] && [ -d "storage/app/public" ]; then
  php artisan storage:link 2>/dev/null || ln -sfn ../storage/app/public public/storage
fi

if [ -n "${APP_KEY}" ]; then
  php artisan config:cache || true
  php artisan route:cache || true
  php artisan view:cache || true
fi

exec "$@"
