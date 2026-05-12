#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/qa365}"
BRANCH="${BRANCH:-main}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.3-fpm}"

restore_app() {
    if [[ -f artisan ]]; then
        php artisan up >/dev/null 2>&1 || true
    fi
}

cd "${APP_DIR}"

trap restore_app EXIT

php artisan down --retry=60 || true

git config --global --add safe.directory "${APP_DIR}"
git fetch origin "${BRANCH}"
git checkout "${BRANCH}"
git pull --ff-only origin "${BRANCH}"

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build

php artisan optimize:clear
php artisan permission:cache-reset || true
php artisan migrate --force
php artisan storage:link || true

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

php artisan route:list --name=quality-forms.update-context --json | grep -q '"name":"quality-forms.update-context"'
php artisan route:list --name=quality-forms.context.download --json | grep -q '"name":"quality-forms.context.download"'

chown -R www-data:www-data storage bootstrap/cache public/build
chmod -R ug+rwX storage bootstrap/cache

if [[ "${FORCE_NGINX:-0}" == "1" || ! -f /etc/nginx/sites-available/qa365 ]]; then
    cp deployment/digitalocean/nginx.conf /etc/nginx/sites-available/qa365
fi

ln -sfn /etc/nginx/sites-available/qa365 /etc/nginx/sites-enabled/qa365
rm -f /etc/nginx/sites-enabled/default
nginx -t

cp deployment/digitalocean/supervisor-workers.conf /etc/supervisor/conf.d/qa365-workers.conf
supervisorctl reread
supervisorctl update
supervisorctl restart qa365-default-worker:* || true
supervisorctl restart qa365-ai-worker:* || true

systemctl reload nginx
systemctl restart "${PHP_FPM_SERVICE}"

php artisan up
php artisan about
