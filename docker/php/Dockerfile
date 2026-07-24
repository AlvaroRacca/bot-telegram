FROM php:8.4-cli-alpine

RUN apk add --no-cache \
        icu-dev \
        oniguruma-dev \
        git \
        unzip \
    && docker-php-ext-install \
        pdo_mysql \
        intl \
        opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

RUN composer install --no-dev --no-interaction --optimize-autoloader --prefer-dist \
    && mkdir -p var/cache var/log

CMD ["php", "bin/console", "app:telegram:poll", "--loop"]
