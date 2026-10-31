FROM php:8.2-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends iputils-ping \
 && rm -rf /var/lib/apt/lists/* \
 && docker-php-ext-install pdo_mysql \
 && a2enmod headers rewrite

COPY docker/php/zz-polyvuln.ini /usr/local/etc/php/conf.d/zz-polyvuln.ini
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/apache/security.conf /etc/apache2/conf-available/security.conf

COPY src/ /var/www/html/
COPY storage/ /var/www/storage/

RUN chown -R www-data:www-data /var/www/html/uploads \
 && chmod -R 755 /var/www/html /var/www/storage

ENV DOC_STORE=/var/www/storage/documents
EXPOSE 80
