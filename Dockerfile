FROM php:8.4-fpm

# Install dependencies for PostgreSQL
RUN apt-get update && apt-get install -y libpq-dev zip unzip git
RUN docker-php-ext-install pdo pdo_pgsql pgsql

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
