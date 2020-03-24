FROM php:7.4-apache

RUN apt-get -y update && apt-get -y install git && apt-get clean

COPY --from=composer:1.9 /usr/bin/composer /usr/bin/composer

COPY src /var/www/html/src
COPY composer.* /var/www/html/
COPY views /var/www/html/views
COPY .htaccess /var/www/html
COPY assets /var/www/html/assets

RUN COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME="/composer" \
    composer install

COPY vendor /var/www/html/vendor

COPY docker/php/vhost.conf /etc/apache2/sites-available/000-default.conf

RUN chown -R www-data:www-data /var/www/html \
    && a2enmod rewrite
RUN docker-php-ext-install pdo_mysql
