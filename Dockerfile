FROM php:8.2-fpm-alpine

# Install dependencies
RUN apk --no-cache add \
    zip \
    unzip \
    curl \
    libpng-dev \
    libzip-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    oniguruma-dev \
    $PHPIZE_DEPS

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    gd \
    zip \
    bcmath \
    opcache \
    mbstring

# Install Redis extension
RUN pecl install redis && \
    docker-php-ext-enable redis

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Install composer dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    composer install --no-scripts --no-autoloader --no-dev

# Copy application files
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize

# Create storage directory and set permissions
RUN mkdir -p storage/framework/{sessions,views,cache} && \
    mkdir -p storage/logs && \
    chmod -R 775 storage bootstrap/cache && \
    chown -R www-data:www-data storage bootstrap/cache

# Set up PHP-FPM configuration
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/custom.ini /usr/local/etc/php/conf.d/custom.ini

# Expose port 9000 for PHP-FPM
EXPOSE 9000

CMD ["php-fpm"]
