#!/bin/bash

# Run Migrations
php artisan migrate --force || true

# Run Seeders
php artisan db:seed --class=RoleSeeder --force || true
php artisan db:seed --class=PermissionSeeder --force || true
php artisan db:seed --class=AdminSeeder --force || true

# Cache Config
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start Services
service nginx start
php-fpm
