FROM php:8.2-cli-bookworm

RUN apt-get update && apt-get install -y git unzip zip && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql pcntl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --optimize-autoloader --no-dev --no-scripts --no-interaction

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} index.php"]
