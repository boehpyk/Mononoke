FROM php:8.3-cli-alpine AS composer-stage

RUN apk add --no-cache unzip git

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

COPY src/ ./src/
COPY examples/ ./examples/

CMD ["php", "examples/http.php"]