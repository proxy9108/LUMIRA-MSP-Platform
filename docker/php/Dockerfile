FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    curl-dev \
    oniguruma-dev

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    curl \
    zip

# Set working directory
WORKDIR /var/www/lumira

# Copy application files
COPY . /var/www/lumira

# Set permissions
RUN chown -R www-data:www-data /var/www/lumira \
    && chmod -R 755 /var/www/lumira

# Expose PHP-FPM port
EXPOSE 9000

CMD ["php-fpm"]
