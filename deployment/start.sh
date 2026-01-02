#!/bin/bash

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
