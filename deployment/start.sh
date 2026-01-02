#!/bin/bash

# Cache Config
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run Migrations
php artisan migrate --force

# Start Service
service nginx start
php-fpm
