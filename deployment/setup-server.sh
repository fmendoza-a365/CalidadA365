#!/bin/bash

###############################################################################
# QA365 - Initial Setup Script
# Run this script ONCE on a fresh server to setup the application
###############################################################################

set -e

echo "======================================"
echo "QA365 Initial Server Setup"
echo "======================================"
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

# Check we're root
if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root (use sudo)"
   exit 1
fi

APP_DIR="/var/www/qa365"
APP_USER="www-data"

# 1. Update system
log_info "Updating system packages..."
apt update && apt upgrade -y

# 2. Install required packages
log_info "Installing required packages..."
apt install -y \
    nginx \
    mysql-server \
    redis-server \
    php8.3-fpm \
    php8.3-cli \
    php8.3-mysql \
    php8.3-redis \
    php8.3-xml \
    php8.3-mbstring \
    php8.3-curl \
    php8.3-zip \
    php8.3-gd \
    php8.3-bcmath \
    supervisor \
    git \
    curl \
    unzip

# 3. Install Composer
log_info "Installing Composer..."
if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
fi

# 4. Install Node.js and npm
log_info "Installing Node.js..."
if ! command -v node &> /dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt install -y nodejs
fi

# 5. Setup MySQL database
log_info "Setting up MySQL database..."
read -p "Enter MySQL root password (or press Enter to skip): " MYSQL_ROOT_PASS

if [ ! -z "$MYSQL_ROOT_PASS" ]; then
    mysql -u root -p"$MYSQL_ROOT_PASS" <<EOF
CREATE DATABASE IF NOT EXISTS qa365_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'qa365_user'@'localhost' IDENTIFIED BY 'CHANGE_THIS_PASSWORD';
GRANT ALL PRIVILEGES ON qa365_production.* TO 'qa365_user'@'localhost';
FLUSH PRIVILEGES;
EOF
    log_info "Database created successfully"
else
    log_warn "Skipped MySQL setup - configure manually"
fi

# 6. Create application directory
log_info "Creating application directory..."
mkdir -p $APP_DIR
chown -R $APP_USER:$APP_USER $APP_DIR

# 7. Configure PHP-FPM
log_info "Configuring PHP-FPM..."
sed -i 's/upload_max_filesize = .*/upload_max_filesize = 10M/' /etc/php/8.3/fpm/php.ini
sed -i 's/post_max_size = .*/post_max_size = 10M/' /etc/php/8.3/fpm/php.ini
sed -i 's/max_execution_time = .*/max_execution_time = 300/' /etc/php/8.3/fpm/php.ini

# 8. Setup Nginx
log_info "Installing Nginx configuration..."
if [ -f "deployment/nginx-config.conf" ]; then
    cp deployment/nginx-config.conf /etc/nginx/sites-available/qa365
    ln -sf /etc/nginx/sites-available/qa365 /etc/nginx/sites-enabled/
    rm -f /etc/nginx/sites-enabled/default
    nginx -t && systemctl reload nginx
    log_info "Nginx configured"
else
    log_warn "Nginx config not found - configure manually"
fi

# 9. Setup queue worker service
log_info "Installing queue worker service..."
if [ -f "deployment/qa365-queue-worker.service" ]; then
    cp deployment/qa365-queue-worker.service /etc/systemd/system/
    systemctl daemon-reload
    systemctl enable qa365-queue-worker
    log_info "Queue worker service installed (not started yet)"
else
    log_warn "Queue worker service file not found"
fi

# 10. Setup SSL with Let's Encrypt (optional)
log_info ""
read -p "Do you want to setup SSL with Let's Encrypt? (y/n): " SETUP_SSL
if [ "$SETUP_SSL" = "y" ]; then
    apt install -y certbot python3-certbot-nginx
    log_info "Certbot installed. Run: sudo certbot --nginx -d your-domain.com"
fi

# 11. Create log directories
log_info "Creating log directories..."
mkdir -p /var/log/nginx
touch /var/log/qa365-queue-worker.log
touch /var/log/qa365-queue-worker-error.log
chown -R $APP_USER:$APP_USER /var/log/qa365-*

# 12. Create backup directory
log_info "Creating backup directory..."
mkdir -p /var/backups/qa365
chown -R $APP_USER:$APP_USER /var/backups/qa365

echo ""
log_info "Server setup completed!"
echo ""
echo "======================================"
echo "Next Steps:"
echo "======================================"
echo "1. Clone your repository to $APP_DIR"
echo "2. Copy .env.production.example to .env and configure"
echo "3. Run: php artisan key:generate"
echo "4. Run: php artisan migrate --force"
echo "5. Run: php artisan db:seed"
echo "6. Configure Nginx domain in /etc/nginx/sites-available/qa365"
echo "7. If using SSL: sudo certbot --nginx -d your-domain.com"
echo "8. Start queue worker: sudo systemctl start qa365-queue-worker"
echo ""
