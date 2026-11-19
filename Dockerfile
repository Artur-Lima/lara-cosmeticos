# syntax=docker/dockerfile:1

# ── Estágio 1: dependências PHP (cache de camada separado do código) ────────
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist --ignore-platform-reqs

# ── Estágio 2: assets (Tailwind + Alpine via Vite) ──────────────────────────
# O Tailwind escaneia resources/views para gerar só as classes usadas; sem o
# manifest gerado aqui, @vite() quebra todas as páginas em produção.
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
# O Tailwind também lê as views de paginação do Laravel (tailwind.config.js).
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

# ── Estágio 3: runtime PHP-FPM ──────────────────────────────────────────────
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
        libonig-dev libxml2-dev libzip-dev libicu-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

WORKDIR /var/www

COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data . .
# Versão do build sempre vence qualquer public/build que exista no disco local.
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && rm -rf bootstrap/cache/*.php \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && echo "clear_env = no" >> /usr/local/etc/php-fpm.d/www.conf

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
