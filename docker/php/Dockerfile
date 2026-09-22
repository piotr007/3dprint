FROM php:8.3-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        curl \
        unzip \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring zip exif gd intl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN mkdir -p /var/www/html/storage/tmp \
    && chown -R www-data:www-data /var/www/html/storage

RUN { \
    echo 'log_errors=On'; \
    echo 'display_errors=Off'; \
    echo 'sys_temp_dir=/var/www/html/storage/tmp'; \
    echo 'upload_tmp_dir=/var/www/html/storage/tmp'; \
} > /usr/local/etc/php/conf.d/99-3dprint.ini
