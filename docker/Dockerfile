## Build Dev
FROM php:7.4-apache as dev-php
RUN apt-get update && apt-get install -y \
        libfreetype6-dev \
        libjpeg-dev \
        libpng-dev \
        libzip-dev \
        libcurl4-gnutls-dev \
        libicu-dev \
        libxml2-dev \
        libssl-dev \
        libonig-dev \
        libldap2-dev \
        zlib1g-dev \
    && rm -r /var/lib/apt/lists/*
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        curl \
        gd \
        intl \
        mbstring \
        pdo_mysql \
        zip
RUN touch .env

## Build Prod
FROM dev-php as prod-php
WORKDIR /var/www/html/
COPY composer.* .
COPY *.php .
COPY .htaccess .
COPY ./public ./public/
COPY ./framework ./framework/
COPY ./framework/settings/application ./application
ENV COMPOSER_ALLOW_SUPERUSER 1
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer update
RUN composer install