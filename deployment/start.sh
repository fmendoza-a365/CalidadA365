#!/bin/bash

# Create SQLite database file if it doesn't exist
if [ ! -f /var/www/html/database/database.sqlite ]; then
    touch /var/www/html/database/database.sqlite
    chmod 664 /var/www/html/database/database.sqlite
fi

# Run Migrations
php artisan migrate --force

# Seed roles and permissions (idempotent)
php artisan db:seed --class=RoleSeeder --force
php artisan db:seed --class=PermissionSeeder --force

# Cache Config
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start Services
service nginx start
php-fpm
