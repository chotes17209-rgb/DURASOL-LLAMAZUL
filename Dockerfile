# ---------- 1) Compilar CSS/JS (Vite + Tailwind) ----------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY app ./app
RUN npm run build

# ---------- 2) Dependencias PHP ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# ---------- 3) Imagen final: PHP-FPM + Nginx ----------
FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev libpq-dev default-mysql-client \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo_mysql pdo_pgsql bcmath intl gd zip opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html
COPY --from=vendor /app ./
COPY --from=assets /app/public/build ./public/build

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80
CMD ["/usr/local/bin/entrypoint.sh"]
