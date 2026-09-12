FROM php:8.4-cli

WORKDIR /var/www/html

# System dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        bcmath \
        intl \
        zip \
        gd \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy files required by Composer/Laravel package discovery
COPY composer.json composer.lock artisan .env.example ./

# Create temporary environment
RUN cp .env.example .env

# Install PHP dependencies
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# Copy application source
COPY . .

# Ensure writable Laravel directories exist
RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data \
        storage \
        bootstrap/cache

# Generate application key
RUN php artisan key:generate --force

# Cache Laravel configuration
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
