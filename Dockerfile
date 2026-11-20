# syntax=docker/dockerfile:1

############################################
# 1) Dependências PHP (sem pacotes de desenvolvimento)
#    Vem antes do build do CSS porque o app.css importa o flux.css de vendor/.
############################################
FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

############################################
# 2) Assets (Vite + Tailwind)
############################################
FROM node:22-alpine AS assets
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
COPY --from=vendor /app/vendor/livewire/flux ./vendor/livewire/flux
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources ./vendor/laravel/framework/src/Illuminate/Pagination/resources
RUN npm run build

############################################
# 3) Imagem final: PHP-FPM + Nginx (porta 8080)
#    https://serversideup.net/open-source/docker-php/
############################################
FROM serversideup/php:8.3-fpm-nginx

# AUTORUN: a cada início do container roda storage:link, migrate --force e os caches do Laravel.
ENV AUTORUN_ENABLED=true \
    PHP_OPCACHE_ENABLE=1 \
    HEALTHCHECK_PATH=/up \
    LOG_CHANNEL=stderr

WORKDIR /var/www/html

COPY --chown=www-data:www-data --from=vendor /app /var/www/html
COPY --chown=www-data:www-data --from=assets /app/public/build /var/www/html/public/build

# Depois das migrations: dados de demonstração ou primeiro admin (só com banco vazio).
USER root
COPY docker/entrypoint.d/ /etc/entrypoint.d/
RUN chmod 755 /etc/entrypoint.d/60-app-bootstrap.sh
USER www-data
