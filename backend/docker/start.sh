#!/bin/sh
# Runs on every container start: update the schema, seed (idempotent), cache config and routes, start Apache.
set -e
cd /var/www/html

php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache

# Files written by the commands above must stay writable by Apache's user.
chown -R www-data:www-data storage bootstrap/cache

exec apache2-foreground
