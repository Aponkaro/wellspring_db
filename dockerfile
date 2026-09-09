FROM php:8.2-apache

# Install PDO MySQL extension needed for db.php
RUN docker-php-ext-install pdo pdo_mysql

# Copy website files to default Apache web directory
COPY . /var/www/html/

# Expose HTTP port
EXPOSE 80