# Imagem de desenvolvimento do EBD: PHP (CLI) + Composer + Node.
# Uma única imagem atende app, vite, worker e scheduler. O plugin Vite do
# Wayfinder executa `php artisan`, por isso o Node precisa conviver com o PHP.
FROM composer:2 AS composer

FROM php:8.5-cli-alpine

ARG UID=1000
ARG GID=1000

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions pdo_pgsql pgsql intl zip bcmath pcntl redis opcache \
    && apk add --no-cache bash git unzip nodejs npm \
    && addgroup -g "${GID}" app \
    && adduser -D -u "${UID}" -G app -h /home/app app

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-ebd.ini

ENV COMPOSER_HOME=/home/app/.composer \
    PHP_CLI_SERVER_WORKERS=4

WORKDIR /var/www/html
USER app

EXPOSE 8000 5173

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
