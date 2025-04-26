FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Setup working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Asegurarnos de que el index.php existe y es accesible
RUN if [ ! -f /var/www/html/public/index.php ]; then \
    echo "ERROR: index.php no encontrado en public/" && exit 1; \
    fi && \
    chmod -R 755 /var/www/html/public && \
    ls -la /var/www/html/public/

# Set permissions
RUN mkdir -p /var/www/html/storage/logs \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/bootstrap/cache \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache

# Install Composer dependencies
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Configure php-fpm to listen on network interface
RUN echo "listen = 9000" > /usr/local/etc/php-fpm.d/www.conf \
    && echo "listen.owner = www-data" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "listen.group = www-data" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "user = www-data" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "group = www-data" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm = dynamic" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.max_children = 10" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.start_servers = 2" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.min_spare_servers = 1" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.max_spare_servers = 3" >> /usr/local/etc/php-fpm.d/www.conf

# Generate optimized files
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Change ownership of all files to www-data
RUN chown -R www-data:www-data /var/www/html

# Expose port 9000
EXPOSE 9000

# Run php-fpm
CMD ["php-fpm"]
