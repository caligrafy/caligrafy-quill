## Build Dev
FROM php:8.5-apache AS dev-php
# Only the libraries needed to build the extensions that php:8.5-apache does not already include
# (curl, mbstring, openssl, xml and zlib are built in): gd (freetype, jpeg, png), intl (icu) and zip (libzip).
# --no-install-recommends keeps the download small. The Debian mirror is used over HTTPS (much faster than plain HTTP
# on some proxied networks), falling back to HTTP when HTTPS to the mirror fails (e.g. an untrusted proxy certificate).
RUN sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources \
    && (apt-get update || (sed -i 's|https://deb.debian.org|http://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources && apt-get update)) \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev \
        libicu-dev \
    && rm -rf /var/lib/apt/lists/*
RUN a2enmod rewrite headers
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        gd \
        intl \
        pdo_mysql \
        zip

## Build Prod
FROM dev-php AS prod-php
WORKDIR /var/www/html/caligrafy-quill
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY ./composer.* ./
COPY .htaccess ./
COPY ./*.php ./
COPY ./framework ./framework/
COPY ./public ./public/
COPY ./.env ./
RUN composer install
RUN php caligrafer.php initialize
COPY ./application ./application/
COPY ./public ./public/
RUN chmod -R 777 ./public/uploads