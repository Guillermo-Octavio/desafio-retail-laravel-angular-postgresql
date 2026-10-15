FROM php:8.5-fpm-alpine

RUN apk add --no-cache libpq-dev \
    && docker-php-ext-install pdo_pgsql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/entrypoint

ENV COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
