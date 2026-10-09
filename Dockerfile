# Imagem de produção (Railway), no lugar do build automático do Railpack.
# Reproduz o que ele montava: FrankenPHP 8.4, as extensões, Composer, build do
# front e o mesmo start (docker/frankenphp). O Node vem da imagem oficial, não
# do mise: em 2026-10-05 o mise do Railpack quebrou ao baixar o plugin do PHP.
# O desenvolvimento usa docker/php/Dockerfile.
FROM dunglas/frankenphp:php8.4.26-trixie AS base

RUN install-php-extensions bcmath intl pdo_pgsql ctype curl dom fileinfo filter hash mbstring openssl pcre pdo session tokenizer xml

COPY docker/frankenphp/php.ini /usr/local/etc/php/conf.d/php.ini
COPY docker/frankenphp/Caddyfile /Caddyfile
COPY --chmod=755 docker/frankenphp/start-container.sh /start-container.sh

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SERVER_NAME=:80 \
    PHP_INI_DIR=/usr/local/etc/php \
    IS_LARAVEL=true

WORKDIR /app

# Dependências PHP e build do front. O plugin Vite do Wayfinder executa
# `php artisan`, então o Node precisa estar junto com o PHP.
FROM base AS build

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=node:24-trixie-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24-trixie-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

COPY composer.json composer.lock ./
RUN composer install --optimize-autoloader --no-scripts --no-interaction --no-autoloader

COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY . .

ARG VITE_APP_NAME=EBD
RUN composer dump-autoload --optimize \
    && npm run build \
    && rm -rf node_modules

FROM base

COPY --from=build /app /app

RUN mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/framework/testing storage/logs bootstrap/cache \
    && chmod -R a+rw storage bootstrap/cache

EXPOSE 80

CMD ["/start-container.sh"]
