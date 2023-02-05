## Build Dev
FROM php:7.2-apache as dev-php
ENV SERVER_NAME Caligrafy-DEV
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    curl \
    openssl \
    mcrypt \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install gd mbstring

## Build Prod
FROM dev-php as prod-php
ENV SERVER_NAME Caligrafy-PROD
COPY ./public /var/www/html/
