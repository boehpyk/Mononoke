# First stage: PHP with Composer
FROM php:8.3-cli-alpine AS composer-stage

# Install system dependencies
RUN apk add --no-cache unzip git

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# Second stage: Lightweight PHP runtime
FROM php:8.3-cli-alpine

WORKDIR /app

# Install system dependencies
RUN apk add --no-cache curl

# Copy app code and dependencies
COPY --from=composer-stage /app /app
COPY . .

# Set the command to run your ReactPHP HTTP server
CMD ["php", "examples/http.php"]