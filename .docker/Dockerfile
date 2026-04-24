FROM php:8.2-cli-alpine

RUN apk add --no-cache \
    git \
    unzip \
    icu-dev \
    libffi-dev \
    autoconf \
    gcc \
    g++ \
    make \
    && docker-php-ext-install intl ffi \
    && pecl install mongodb redis \
    && docker-php-ext-enable mongodb redis \
    && apk del autoconf gcc g++ make \
    && adduser -D -u 1000 app \
    && mkdir -p /home/app/.composer \
    && chown -R app:app /home/app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

USER app
