FROM composer:latest AS composer

FROM dunglas/frankenphp:php8.2.31-bookworm

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN docker-php-ext-install pdo_mysql

WORKDIR /app
COPY . .

RUN composer install --optimize-autoloader --no-dev --no-scripts --no-interaction

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} index.php"]
