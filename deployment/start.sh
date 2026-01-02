#!/bin/bash

# Fix permissions FIRST - Railway keeps resetting them
mkdir -p /var/www/html/storage/framework/{cache,sessions,views,testing}
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 777 /var/www/html/storage
chmod -R 777 /var/www/html/bootstrap/cache

# Run Migrations
php artisan migrate --force || true

# Run Seeders
php artisan db:seed --class=RoleSeeder --force || true
php artisan db:seed --class=PermissionSeeder --force || true
php artisan db:seed --class=AdminSeeder --force || true

# Cache Config
php artisan config:cache
php artisan view:cache

# Configure Nginx with dynamic PORT
export PORT=${PORT:-8080}
sed -i "s/\${PORT}/$PORT/g" /etc/nginx/sites-available/default

# Start Services
service nginx start
php-fpm
