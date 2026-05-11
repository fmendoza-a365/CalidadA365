#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/qa365}"
BRANCH="${BRANCH:-main}"

cd "${APP_DIR}"

php artisan down --retry=60 || true

git config --global --add safe.directory "${APP_DIR}"
git fetch origin "${BRANCH}"
git checkout "${BRANCH}"
git pull --ff-only origin "${BRANCH}"

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build

php artisan migrate --force
php artisan storage:link || true

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

chown -R www-data:www-data storage bootstrap/cache public/build
chmod -R ug+rwX storage bootstrap/cache

cp deployment/digitalocean/nginx.conf /etc/nginx/sites-available/qa365
ln -sfn /etc/nginx/sites-available/qa365 /etc/nginx/sites-enabled/qa365
rm -f /etc/nginx/sites-enabled/default
nginx -t

cp deployment/digitalocean/supervisor-workers.conf /etc/supervisor/conf.d/qa365-workers.conf
supervisorctl reread
supervisorctl update
supervisorctl restart qa365-default-worker:* || true
supervisorctl restart qa365-ai-worker:* || true

systemctl reload nginx
systemctl reload php8.3-fpm

php artisan up
php artisan about
