FROM php:8.3-fpm-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql zip \
    && pecl install redis-6.2.0 && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
COPY docker/php/app.ini /usr/local/etc/php/conf.d/app.ini
COPY app /app
COPY frontend /frontend
RUN mkdir -p /data && chown www-data:www-data /data
WORKDIR /app
