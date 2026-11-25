FROM php:8.4-cli-alpine

RUN docker-php-ext-install pdo_mysql \
    && apk add --no-cache libzip-dev unzip \
    && docker-php-ext-install zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
ENV PHP_CLI_SERVER_WORKERS=4

COPY docker/start.sh /usr/local/bin/start
RUN chmod +x /usr/local/bin/start

EXPOSE 8000
CMD ["start"]
