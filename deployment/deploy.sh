#!/bin/bash

###############################################################################
# QA365 - Production Deployment Script
# This script deploys the application to production safely
###############################################################################

set -e  # Exit on any error

echo "======================================"
echo "QA365 Production Deployment"
echo "======================================"
echo ""

# Configuration
APP_DIR="/var/www/qa365"
BACKUP_DIR="/var/backups/qa365"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# 1. Check we're running as www-data or root
if [[ $EUID -ne 0 ]] && [[ $(whoami) != "www-data" ]]; then
   log_error "This script must be run as root or www-data user"
   exit 1
fi

# 2. Create backup
log_info "Creating backup..."
mkdir -p $BACKUP_DIR
cd $APP_DIR

# Backup database
if [ -f ".env" ]; then
    log_info "Backing up database..."
    php artisan backup:run --only-db --filename="backup_${TIMESTAMP}.zip" || log_warn "Backup command not available"
fi

# Backup code
log_info "Backing up application files..."
tar -czf "${BACKUP_DIR}/app_${TIMESTAMP}.tar.gz" \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    .

# 3. Enable maintenance mode
log_info "Enabling maintenance mode..."
php artisan down --retry=60 --secret="deployment-$(date +%s)" || true

# 4. Pull latest code
log_info "Pulling latest code from repository..."
git pull origin main || log_error "Failed to pull latest code"

# 5. Install dependencies
log_info "Installing PHP dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

log_info "Installing Node dependencies..."
npm ci

# 6. Build assets
log_info "Building frontend assets..."
npm run build

# 7. Run migrations
log_info "Running database migrations..."
php artisan migrate --force

# 8. Clear and cache
log_info "Clearing old cache..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

log_info "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 9. Set permissions
log_info "Setting correct permissions..."
chown -R www-data:www-data $APP_DIR
chmod -R 755 $APP_DIR
chmod -R 775 $APP_DIR/storage
chmod -R 775 $APP_DIR/bootstrap/cache

# 10. Restart services
log_info "Restarting services..."
systemctl restart php8.3-fpm
systemctl restart qa365-queue-worker || log_warn "Queue worker service not found"

# 11. Disable maintenance mode
log_info "Disabling maintenance mode..."
php artisan up

# 12. Run post-deployment health check
log_info "Running health check..."
curl -f http://localhost/up || log_warn "Health check failed"

echo ""
log_info "Deployment completed successfully!"
log_info "Backup saved to: ${BACKUP_DIR}/app_${TIMESTAMP}.tar.gz"
echo ""
echo "======================================"
echo "Deployment Summary:"
echo "======================================"
echo "Time: $TIMESTAMP"
echo "Previous backup: ${BACKUP_DIR}/app_${TIMESTAMP}.tar.gz"
echo "Application URL: $(grep APP_URL .env | cut -d '=' -f2)"
echo ""
