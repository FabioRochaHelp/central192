FROM php:8.3-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    libicu-dev \
    libpq-dev \
    libzip-dev \
    unzip \
    zip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        bcmath \
        pcntl \
        pdo_pgsql \
        zip \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/docker-entrypoint.sh /usr/local/bin/laravel-docker-entrypoint.sh
RUN chmod +x /usr/local/bin/laravel-docker-entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["laravel-docker-entrypoint.sh"]
CMD ["php-fpm"]
