# Production image of a Caligrafy Quill project: Apache + PHP 8.5, the libraries of composer.lock, no development
# packages. Hosts that build from a Dockerfile (Coolify, Caligrafy Cloud, Railway, Render...) use this file.
# Settings are not in the image (Dockerfile.dockerignore leaves .env out): give the container its .env as a mounted
# file at /var/www/html/.env. At start, `php caligrafer.php deploy` prepares the database, then Apache serves the app.
# (docker/Dockerfile is the development image of docker-compose.yaml.)
FROM php:8.5-apache

RUN sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources \
    && apt-get update \
    && apt-get install -y --no-install-recommends libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev libicu-dev unzip git \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 bcmath exif gd intl mysqli pdo_mysql zip \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html

# libraries first, so that a code change does not reinstall them
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p public/uploads backups \
    && chown -R www-data:www-data public/uploads \
    && chmod +x docker/production-start.sh

EXPOSE 80
CMD ["docker/production-start.sh"]
