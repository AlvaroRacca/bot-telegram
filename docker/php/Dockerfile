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

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --optimize-autoloader --prefer-dist

COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p var/cache var/log

CMD ["php", "bin/console", "app:telegram:poll", "--loop"]
