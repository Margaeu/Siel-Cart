#!/bin/bash
APP_ROOT="/home/site/wwwroot"

# Create writable dirs for Laravel
mkdir -p "${APP_ROOT}/storage/framework/sessions" \
         "${APP_ROOT}/storage/framework/views" \
         "${APP_ROOT}/storage/framework/cache" \
         "${APP_ROOT}/bootstrap/cache"
chmod -R 775 "\({APP_ROOT}/storage" "\){APP_ROOT}/bootstrap/cache"

# Copy Nginx config directly
cp "${APP_ROOT}/nginx.conf" /etc/nginx/sites-available/default
service nginx reload

# Clear and rebuild Laravel caches
php "${APP_ROOT}/artisan" config:clear || true
php "${APP_ROOT}/artisan" route:clear || true
php "${APP_ROOT}/artisan" view:clear || true