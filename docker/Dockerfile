# Stage 1: dependencies, and the folders of the modules this image doesn't run removed.
FROM composer:2 AS vendor

# '*' keeps every module; a list (e.g. "analytics") builds an image for those modules only.
ARG RUN_MODULES=*

WORKDIR /app

# The packages from a local path (see "repositories" in composer.json); empty outside the monorepo.
COPY --from=packages . /packages

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

COPY . .
# The packages only the purged modules required (their own composer.json) are removed; naming them keeps every other version as locked.
RUN composer dump-autoload --no-dev \
    && UNUSED="$(RUN_MODULES="$RUN_MODULES" php artisan modules:unused-packages)" \
    && RUN_MODULES="$RUN_MODULES" php artisan modules:purge --force \
    && if [ -n "$UNUSED" ]; then \
        composer update $UNUSED --no-dev --no-scripts --no-autoloader --ignore-platform-reqs \
        && rm -f bootstrap/cache/packages.php bootstrap/cache/services.php; \
    fi \
    && composer dump-autoload --optimize --no-dev

# Stage 2: PHP with Swoole, for Octane.
FROM php:8.4-cli AS runtime

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions swoole pcntl pdo_pgsql redis opcache intl

# curl is for the healthcheck only.
RUN apt-get update && apt-get install -y --no-install-recommends supervisor curl \
    && rm -rf /var/lib/apt/lists/*

ARG SUPERCRONIC_VERSION=v0.2.33
ARG TARGETARCH
RUN curl -fsSL "https://github.com/aptible/supercronic/releases/download/${SUPERCRONIC_VERSION}/supercronic-linux-${TARGETARCH}" \
    -o /usr/local/bin/supercronic && chmod +x /usr/local/bin/supercronic

ARG RUN_MODULES=*
ENV RUN_MODULES=${RUN_MODULES} \
    OCTANE_SERVER=swoole \
    WITH_HTTP=true \
    WITH_RPC=false \
    WITH_WORKER=false \
    WITH_SCHEDULER=false \
    WITH_CONSUMERS=false

WORKDIR /app

COPY --from=vendor /packages /packages
COPY --from=vendor /app /app

RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-distributable.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]
