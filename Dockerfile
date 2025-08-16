FROM --platform=linux/amd64 php:8.4-cli-alpine

RUN apk add --no-cache unzip git inotify-tools bash \
    && docker-php-ext-install pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

COPY src/ ./src/
COPY examples/ ./examples/

CMD ["src/bin/mononoke", "/app/examples/sns.php"]