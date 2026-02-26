#!/bin/sh
set -e

echo "🔧 Running database migrations..."
php artisan migrate:fresh --force

echo "🌱 Running seeders..."
php artisan db:seed --class=RoleSeeder --force
php artisan db:seed --class=PermissionSeeder --force  
php artisan db:seed --class=AdminSeeder --force

echo "✨ Setup complete!"
