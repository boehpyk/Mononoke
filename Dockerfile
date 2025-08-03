FROM php:8.4-cli-alpine

RUN apk add --no-cache unzip git inotify-tools bash

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

COPY src/ ./src/
COPY examples/ ./examples/

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

CMD ["/entrypoint.sh"]