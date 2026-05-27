#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/qa365}"
BRANCH="${BRANCH:-main}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.3-fpm}"
BACKUP_BEFORE_MIGRATE="${BACKUP_BEFORE_MIGRATE:-1}"
BACKUP_DIR="${BACKUP_DIR:-${APP_DIR}/storage/app/backups/releases}"

restore_app() {
    if [[ -f artisan ]]; then
        php artisan up >/dev/null 2>&1 || true
    fi
}

backup_database() {
    if [[ "${BACKUP_BEFORE_MIGRATE}" != "1" ]]; then
        echo "Database backup skipped because BACKUP_BEFORE_MIGRATE=${BACKUP_BEFORE_MIGRATE}."
        return
    fi

    mkdir -p "${BACKUP_DIR}"

    mapfile -t db_config < <(php -r '
        require __DIR__."/vendor/autoload.php";
        $app = require __DIR__."/bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $connection = config("database.default");
        $config = config("database.connections.$connection", []);
        foreach ([
            $connection,
            $config["host"] ?? "",
            $config["port"] ?? "",
            $config["database"] ?? "",
            $config["username"] ?? "",
            $config["password"] ?? "",
        ] as $value) {
            echo str_replace(["\r", "\n"], "", (string) $value).PHP_EOL;
        }
    ')

    local connection="${db_config[0]:-}"
    local host="${db_config[1]:-127.0.0.1}"
    local port="${db_config[2]:-}"
    local database="${db_config[3]:-}"
    local username="${db_config[4]:-}"
    local password="${db_config[5]:-}"
    local timestamp
    timestamp="$(date +%Y%m%d_%H%M%S)"

    case "${connection}" in
        pgsql)
            command -v pg_dump >/dev/null 2>&1 || { echo "pg_dump is required before running migrations."; exit 1; }
            local backup_file="${BACKUP_DIR}/qa365_${timestamp}.pgsql.sql.gz"
            PGPASSWORD="${password}" pg_dump --no-owner --no-privileges -h "${host}" -p "${port:-5432}" -U "${username}" "${database}" | gzip > "${backup_file}"
            chmod 640 "${backup_file}"
            echo "Database backup created: ${backup_file}"
            ;;
        mysql|mariadb)
            command -v mysqldump >/dev/null 2>&1 || { echo "mysqldump is required before running migrations."; exit 1; }
            local backup_file="${BACKUP_DIR}/qa365_${timestamp}.mysql.sql.gz"
            MYSQL_PWD="${password}" mysqldump -h "${host}" -P "${port:-3306}" -u "${username}" --single-transaction --routines --triggers "${database}" | gzip > "${backup_file}"
            chmod 640 "${backup_file}"
            echo "Database backup created: ${backup_file}"
            ;;
        sqlite)
            local backup_file="${BACKUP_DIR}/qa365_${timestamp}.sqlite"
            cp "${database}" "${backup_file}"
            chmod 640 "${backup_file}"
            echo "Database backup created: ${backup_file}"
            ;;
        *)
            echo "Unsupported DB_CONNECTION=${connection}; refusing to migrate without a backup."
            exit 1
            ;;
    esac
}

cd "${APP_DIR}"

trap restore_app EXIT

php artisan down --retry=60 || true

git config --global --add safe.directory "${APP_DIR}"
git fetch origin "${BRANCH}"
git checkout -B "${BRANCH}" "origin/${BRANCH}"
git reset --hard "origin/${BRANCH}"
git clean -fd public/build

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
php artisan optimize:clear
backup_database
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
php artisan qa:health --json || true
php artisan about
