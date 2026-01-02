FROM php:8.3-fpm

# Install system dependencies (Force rebuild)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    nginx \
    nodejs \
    npm \
    gettext-base \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions (without intl first)
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd zip

# Install intl separately with explicit configuration
RUN docker-php-ext-configure intl && docker-php-ext-install intl

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy existing application directory contents
COPY . /var/www/html

# Copy existsing application directory permissions
COPY --chown=www-data:www-data . /var/www/html

# Create system user to run Composer and Artisan Commands
RUN usermod -u 1000 www-data

# Configure Nginx
COPY deployment/nginx.conf /etc/nginx/sites-available/default

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Install Node dependencies and build assets
RUN npm install && npm run build

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port 80
EXPOSE 80

# Start Nginx and PHP-FPM
COPY deployment/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

CMD ["/usr/local/bin/start.sh"]
