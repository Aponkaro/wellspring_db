FROM php:8.2-apache

# Install PDO MySQL extensions
RUN docker-php-ext-install pdo pdo_mysql && docker-php-ext-enable pdo_mysql

# Enable Apache Mod Rewrite
RUN a2enmod rewrite

# Copy system code to Apache web root
COPY . /var/www/html/

# Set working directory permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80