#!/usr/bin/env bash
#
# Azure App Service (Linux, built-in PHP 8.2 image) startup command:
#
#   bash /home/site/wwwroot/startup.sh
#
# Runs on every container start, so it only does what a start needs: install
# the repository's Nginx site, optionally migrate, rebuild Laravel's caches,
# and fix permissions. Dependencies and frontend assets are built in GitHub
# Actions and arrive in the deployed package -- never install or build here,
# because F1 has 60 CPU minutes a day and a cold start would burn them.
#
# Any failure aborts the start instead of leaving a half-configured app
# serving requests (stale config, missing tables, or the stock Nginx site
# that does not route through public/index.php).

set -Eeuo pipefail

trap 'echo "startup.sh failed at line ${LINENO}: ${BASH_COMMAND}" >&2' ERR

APP_ROOT=/home/site/wwwroot

cd "$APP_ROOT"

# Runtime directories Laravel expects. The package excludes their contents
# (logs, compiled views, file sessions), so a fresh deploy may lack them.
mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    bootstrap/cache

# Writable only where Laravel writes, and only by the PHP worker's group.
# Never 777: that would let any process in the container rewrite compiled
# views or cached config, which PHP then executes.
fix_permissions() {
    if id www-data >/dev/null 2>&1; then
        chown -R www-data:www-data storage bootstrap/cache
    fi

    find storage bootstrap/cache -type d -exec chmod 775 {} +
    find storage bootstrap/cache -type f -exec chmod 664 {} +

    # The Aiven CA must stay readable by PHP but not writable by it, so a
    # compromised worker cannot swap in a certificate it controls.
    if [ -f storage/certs/aiven-ca.pem ]; then
        if id www-data >/dev/null 2>&1; then
            chown root:www-data storage/certs/aiven-ca.pem
        fi
        chmod 640 storage/certs/aiven-ca.pem
    fi
}

fix_permissions

# Install the repository's Nginx site. `default` is the canonical file;
# nginx.conf is a compatibility copy kept identical by the deploy workflow.
cp "$APP_ROOT/default" /etc/nginx/sites-available/default
ln -sfn /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default
nginx -t

# Clear every cache first, so a previous deployment's cached config cannot
# point the migration below at stale database settings.
php artisan optimize:clear

if [ "${RUN_MIGRATIONS_ON_STARTUP:-false}" = "true" ]; then
    # --isolated takes a cache lock (file store here) so two starting
    # containers cannot migrate at once. Aiven's free tier can be slow to
    # accept a first connection after idling, so retry a bounded number of
    # times and then fail the start rather than serve a stale schema.
    max_attempts=5
    attempt=1

    until php artisan migrate --force --isolated; do
        if [ "$attempt" -ge "$max_attempts" ]; then
            echo "Migrations failed after ${max_attempts} attempts; aborting startup." >&2
            exit 1
        fi

        echo "Migration attempt ${attempt}/${max_attempts} failed; retrying in 10 seconds." >&2
        attempt=$((attempt + 1))
        sleep 10
    done
fi

php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache

# The cache commands ran as root; hand the new files to the PHP worker.
fix_permissions

service nginx reload
