FROM php:7.3-apache

COPY src /var/www/html/src
COPY vendor /var/www/html/vendor
COPY views /var/www/html/views
COPY composer.* /var/www/html/
COPY .htaccess /var/www/html
COPY config.json /var/www/html
COPY assets /var/www/html/assets

RUN mkdir -p /var/www/html/database/github_commits && \
	mkdir -p /var/www/html/database/github_master_data && \
	mkdir -p /var/www/html/database/github_pr_data && \
	mkdir -p /var/www/html/database/pr_comments && \
	mkdir -p /var/www/html/database/pr_reviews && \
	mkdir -p /var/www/html/database/psalm_data && \
	mkdir -p /var/www/html/database/psalm_master_data

COPY docker/php/vhost.conf /etc/apache2/sites-available/000-default.conf

RUN chown -R www-data:www-data /var/www/html \
    && a2enmod rewrite