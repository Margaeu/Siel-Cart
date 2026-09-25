#!/bin/bash
APP_ROOT="/home/site/wwwroot"

# Writable dirs for Laravel
mkdir -p "${APP_ROOT}/storage/framework/sessions" \
         "${APP_ROOT}/storage/framework/views" \
         "${APP_ROOT}/storage/framework/cache" \
         "${APP_ROOT}/bootstrap/cache"
chmod -R 775 "${APP_ROOT}/storage" "${APP_ROOT}/bootstrap/cache"

# Use our own nginx config (root -> /public, Laravel routing)
cp "${APP_ROOT}/nginx.conf" /etc/nginx/sites-available/default
if nginx -t; then
    service nginx reload
else
    echo "nginx config test FAILED - check nginx.conf"
fi

# Laravel caches (env vars must already be set in Azure)
php "${APP_ROOT}/artisan" config:cache || true
php "${APP_ROOT}/artisan" route:cache || true
php "${APP_ROOT}/artisan" view:cache || true