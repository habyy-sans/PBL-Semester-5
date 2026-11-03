FROM php:8.4-fpm

# ekstensi yang dibutuhkan Laravel, Excel/PDF, dan kedua database
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libpq-dev libicu-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo_mysql pdo_pgsql gd zip bcmath intl opcache pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY deploy/docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/app
