# Use the official PHP 8.1 CLI image
FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libzip-dev \
    sqlite3 \
    libsqlite3-dev

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_sqlite

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy the application code into the container
COPY . /app

# Install PHP dependencies
RUN composer install --prefer-dist --no-interaction

# Run the PHPUnit tests by default
CMD ["./vendor/bin/phpunit"]
