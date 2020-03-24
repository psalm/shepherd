FROM php:7.4-apache

RUN apk add --no-cache tini git

COPY --from=composer:1.9 /usr/bin/composer /usr/bin/composer

RUN COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME="/composer" \
    composer install

COPY src /var/www/html/src
COPY vendor /var/www/html/vendor
COPY views /var/www/html/views
COPY composer.* /var/www/html/
COPY .htaccess /var/www/html
COPY assets /var/www/html/assets

COPY docker/php/vhost.conf /etc/apache2/sites-available/000-default.conf

RUN chown -R www-data:www-data /var/www/html \
    && a2enmod rewrite
RUN docker-php-ext-install pdo_mysql
