#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/qa365"
PHP_VERSION="8.3"
SWAP_SIZE="${SWAP_SIZE:-2G}"

if [[ "${EUID}" -ne 0 ]]; then
    echo "Run this script as root: sudo bash deployment/digitalocean/setup-droplet.sh"
    exit 1
fi

apt-get update
apt-get upgrade -y

apt-get install -y software-properties-common ca-certificates curl gnupg unzip git acl ufw
add-apt-repository ppa:ondrej/php -y
apt-get update

apt-get install -y \
    nginx supervisor postgresql postgresql-contrib redis-server certbot python3-certbot-nginx \
    "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-pgsql" \
    "php${PHP_VERSION}-redis" "php${PHP_VERSION}-xml" "php${PHP_VERSION}-mbstring" \
    "php${PHP_VERSION}-curl" "php${PHP_VERSION}-zip" "php${PHP_VERSION}-gd" \
    "php${PHP_VERSION}-bcmath" "php${PHP_VERSION}-intl"

if [[ ! -f /swapfile ]]; then
    fallocate -l "${SWAP_SIZE}" /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

if ! command -v composer >/dev/null 2>&1; then
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi

if ! command -v node >/dev/null 2>&1; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y nodejs
fi

mkdir -p "${APP_DIR}"
chown -R www-data:www-data "${APP_DIR}"

cat > "/etc/php/${PHP_VERSION}/fpm/conf.d/99-qa365.ini" <<'EOF'
upload_max_filesize=100M
post_max_size=120M
memory_limit=512M
max_execution_time=300
max_input_time=300
opcache.enable=1
opcache.enable_cli=0
opcache.validate_timestamps=0
opcache.memory_consumption=192
opcache.max_accelerated_files=20000
EOF

systemctl enable nginx
systemctl enable "php${PHP_VERSION}-fpm"
systemctl enable supervisor
systemctl enable postgresql
systemctl enable redis-server

ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

echo "Base droplet setup complete."
echo "Next: clone the repo into ${APP_DIR}, create .env, create the PostgreSQL user/database, then run deployment/digitalocean/deploy.sh."
