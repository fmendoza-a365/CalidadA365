#!/bin/bash

# Run Migrations
php artisan migrate --force || true

# Cache Config
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start Services
service nginx start
php-fpm
