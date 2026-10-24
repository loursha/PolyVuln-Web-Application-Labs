FROM php:8.3-apache

# PDO MySQL + the extensions the app uses
RUN docker-php-ext-install pdo_mysql \
 && apt-get update && apt-get install -y --no-install-recommends iputils-ping libonig-dev \
 && docker-php-ext-install mbstring \
 && rm -rf /var/lib/apt/lists/*

# Serve from /public and allow the front-controller to handle all routes
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
 && a2enmod rewrite headers
COPY docker/apache-app.conf /etc/apache2/conf-available/app.conf
RUN a2enconf app

WORKDIR /var/www/html
COPY . /var/www/html
RUN mkdir -p logs storage/invoices storage/uploads && chown -R www-data:www-data logs storage
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
EXPOSE 80
CMD ["entrypoint.sh"]
