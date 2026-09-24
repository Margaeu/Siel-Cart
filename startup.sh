#!/bin/bash
set -e

APP_ROOT=/home/site/wwwroot

# Point Nginx to Laravel's public directory
cp /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default
sed -i 's|root /home/site/wwwroot;|root /home/site/wwwroot/public;|g' /etc/nginx/sites-enabled/default

# Storage and cache permissions
chmod -R 777 "\({APP_ROOT}/storage" "\){APP_ROOT}/bootstrap/cache"

# Framework & Filament runtime optimizations.
php "${APP_ROOT}/artisan" config:cache
php "${APP_ROOT}/artisan" route:cache
php "${APP_ROOT}/artisan" view:cache
php "${APP_ROOT}/artisan" filament:upgrade --no-interaction

# Reload Nginx service
service nginx reload