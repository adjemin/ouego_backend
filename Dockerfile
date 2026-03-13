FROM php:8.1-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libxml2-dev \
    libzip-dev \
    postgresql-dev \
    oniguruma-dev \
    icu-dev \
    zip \
    unzip \
    bash

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    xml \
    zip \
    gd \
    bcmath \
    pcntl \
    intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first (cache optimization)
COPY composer.json composer.lock ./

# Install PHP dependencies (no dev, optimized)
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Copy application files
COPY . .

# Run post-install scripts
RUN composer run-script post-autoload-dump

# Copy config files
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/start.sh /usr/local/bin/start.sh

# Set permissions
RUN chmod +x /usr/local/bin/start.sh \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80

CMD ["/usr/local/bin/start.sh"]
