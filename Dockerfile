FROM php:7.3-apache

COPY database /app/public/database
COPY src /app/public/src
COPY vendor /app/public/vendor
COPY views /app/public/views
COPY composer.* /app/public/
COPY .htaccess /app/public
COPY config.json /app/public

COPY docker/php/vhost.conf /etc/apache2/sites-available/000-default.conf

RUN chown -R www-data:www-data /var/www/html \
    && a2enmod rewrite