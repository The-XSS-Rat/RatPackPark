# Stage 1: Build PHP dependencies using Composer
FROM composer:2 as composer
WORKDIR /app

# Copy composer files and install only production dependencies
COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader

# Stage 2: PHP + Apache
FROM php:8.2-apache

# Install system dependencies and PHP extensions
RUN apt-get update && \
    apt-get install -y unzip git curl && \
    docker-php-ext-install pdo pdo_mysql mysqli && \
    a2enmod rewrite

# Set working directory and copy application source
WORKDIR /var/www/html
COPY . /var/www/html/

# Copy vendor directory from Composer build stage
COPY --from=composer /app/vendor /var/www/html/vendor

# Enable .htaccess usage
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Fix permissions
RUN chown -R www-data:www-data /var/www/html

# Expose Apache port
EXPOSE 80
