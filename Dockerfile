FROM php:8.4-cli

# Install dependencies for Swoole + build tools
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    inotify-tools \
    brotli \
    libbrotli-dev \
    zlib1g-dev \
    build-essential \
    autoconf \
    bash \
    && rm -rf /var/lib/apt/lists/*

# Enable pcntl
RUN docker-php-ext-install pcntl

# Install and enable swoole
RUN pecl install swoole \
    && docker-php-ext-enable swoole

# Copy composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# Copy application code
COPY src/ ./src/
COPY examples/ ./examples/

ENTRYPOINT ["src/bin/mononoke"]