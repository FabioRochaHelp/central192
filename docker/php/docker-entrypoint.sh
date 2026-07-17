#!/bin/sh
set -e
cd /var/www/html

mkdir -p \
  storage/framework/views \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/logs \
  bootstrap/cache

# Bind mount do host: garantir escrita para PHP-FPM (www-data, uid 33)
if chown -R www-data:www-data storage bootstrap/cache 2>/dev/null; then
  chmod -R ug+rwX storage bootstrap/cache
else
  chmod -R a+rwX storage bootstrap/cache 2>/dev/null || true
fi

exec docker-php-entrypoint "$@"
