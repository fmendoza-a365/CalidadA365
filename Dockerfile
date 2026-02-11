# Build Stage
FROM node:20 as build

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# Production Stage
FROM php:8.3-fpm

# Install system dependencies
# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    nginx \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions (using mlocati installer for better compatibility and speed)
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql mbstring exif pcntl bcmath gd intl zip opcache

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .
COPY --from=build /app/public/build ./public/build

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Copy Nginx config
COPY deployment/nginx-docker.conf /etc/nginx/sites-available/default

# Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Expose port
EXPOSE 80

# Start Nginx and PHP-FPM
CMD sh -c "nginx && docker-php-entrypoint php-fpm"
