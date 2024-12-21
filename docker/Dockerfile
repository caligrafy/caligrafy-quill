## Build Dev
FROM php:8.2.2-apache AS dev-php
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
RUN cp /etc/apache2/mods-available/rewrite.load /etc/apache2/mods-enabled/ && \
    cp /etc/apache2/mods-available/headers.load /etc/apache2/mods-enabled/
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        curl \
        gd \
        intl \
        mbstring \
        pdo_mysql \
        zip

## Build Prod
FROM dev-php AS prod-php
WORKDIR /var/www/html/
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
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