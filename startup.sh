#!/bin/bash
# Azure App Service (Linux, PHP built-in image) startup command for Siel Cart.
#
# Set as the App Service "Startup Command" (Configuration -> General settings):
#   bash /home/site/wwwroot/startup.sh
#
# Runs on every container start, so it only does things that are safe to
# repeat (document root, permissions, framework caches) — not migrations,
# which belong in the deploy pipeline so they run exactly once per release.

set -e

APP_ROOT=/home/site/wwwroot

# Laravel's public/ is the document root; the built-in image serves
# /var/www/html by default, so point Apache at public/ instead.
sed -i -e "s,/var/www/html,${APP_ROOT}/public,g" /etc/apache2/sites-available/000-default.conf
a2enmod rewrite
sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
service apache2 reload

# storage/ and bootstrap/cache/ must be writable by the web server.
chmod -R 775 "${APP_ROOT}/storage" "${APP_ROOT}/bootstrap/cache"

# App Settings are already real environment variables by this point, so
# it's safe to bake them into the framework caches on every boot.
php "${APP_ROOT}/artisan" config:cache
php "${APP_ROOT}/artisan" route:cache
php "${APP_ROOT}/artisan" view:cache
