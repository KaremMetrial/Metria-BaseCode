# syntax=docker/dockerfile:1.7
# =============================================================================
# Laravel PHP-FPM Image
# -----------------------------------------------------------------------------
# Stages:
#   base        - shared runtime: extensions, user, configuration
#   development - base + Xdebug + Composer + dev php.ini   (bind-mounted source)
#   vendor      - Composer dependency resolution (no-dev, optimized)
#   assets      - Frontend build (Vite / Node 22)
#   production  - hardened, self-contained runtime image
#
# Laravel 11 / 12 / future compatible. Nothing here is app-specific.
# =============================================================================

ARG PHP_VERSION=8.4
ARG COMPOSER_VERSION=2.8
ARG NODE_VERSION=22

FROM composer:${COMPOSER_VERSION} AS composer_bin
FROM mlocati/php-extension-installer:2 AS ext_installer


# =============================================================================
# BASE
# =============================================================================
FROM php:${PHP_VERSION}-fpm-bookworm AS base

LABEL org.opencontainers.image.title="laravel-php-fpm" \
      org.opencontainers.image.description="Production Laravel PHP 8.4 FPM runtime" \
      org.opencontainers.image.licenses="MIT"

ARG UID=1000
ARG GID=1000
ARG USER=app
ARG TZ=UTC

ENV TZ=${TZ} \
    APP_USER=${USER} \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_HOME=/tmp/composer \
    PHP_INI_DIR=/usr/local/etc/php

# Supervisor reads these via %(ENV_*)s. Defaults live here so the image is
# self-sufficient; compose overrides them per environment.
ENV SUPERVISOR_ROLE=queue \
    QUEUE_WORKERS=2 \
    QUEUE_NAME=default \
    QUEUE_TRIES=3 \
    QUEUE_BACKOFF=5 \
    QUEUE_TIMEOUT=60 \
    QUEUE_SLEEP=3 \
    QUEUE_MAX_TIME=3600 \
    QUEUE_MAX_JOBS=1000 \
    QUEUE_MEMORY=256

WORKDIR /var/www/html

# --- System packages -------------------------------------------------------
# libfcgi-bin provides cgi-fcgi, used for a *real* FPM healthcheck (not `-t`).
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        gosu \
        libfcgi-bin \
        procps \
        supervisor \
        tzdata \
        unzip \
        zip; \
    ln -snf "/usr/share/zoneinfo/${TZ}" /etc/localtime; \
    echo "${TZ}" > /etc/timezone; \
    rm -rf /var/lib/apt/lists/*

# --- PHP extensions --------------------------------------------------------
# install-php-extensions resolves build deps, compiles, then strips them again,
# and pins PECL versions that are actually compatible with this PHP release.
COPY --from=ext_installer /usr/bin/install-php-extensions /usr/local/bin/
RUN set -eux; \
    install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        mysqli \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        pgsql \
        redis \
        soap \
        sockets \
        zip \
        imagick; \
    rm -rf /tmp/*

# --- Application user ------------------------------------------------------
# Idempotent across UID/GID overrides: reuses an existing id when present.
RUN set -eux; \
    if ! getent group "${GID}" >/dev/null; then groupadd --gid "${GID}" "${USER}"; fi; \
    if ! getent passwd "${UID}" >/dev/null; then \
        useradd --uid "${UID}" --gid "${GID}" --create-home --shell /bin/bash "${USER}"; \
    fi; \
    GROUP_NAME="$(getent group "${GID}" | cut -d: -f1)"; \
    USER_NAME="$(getent passwd "${UID}" | cut -d: -f1)"; \
    mkdir -p /var/www/html /var/log/supervisor /var/run/supervisor "${COMPOSER_HOME}"; \
    chown -R "${USER_NAME}:${GROUP_NAME}" /var/www/html /var/log/supervisor /var/run/supervisor "${COMPOSER_HOME}"

# --- Configuration ---------------------------------------------------------
COPY docker/php/php.ini            /usr/local/etc/php/conf.d/00-laravel.ini
COPY docker/php/conf.d/opcache.ini /usr/local/etc/php/conf.d/10-opcache.ini
COPY docker/php/www.conf           /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/entrypoint.sh      /usr/local/bin/entrypoint
COPY docker/php/healthcheck.sh     /usr/local/bin/php-fpm-healthcheck
COPY docker/supervisor/            /etc/supervisor/

RUN set -eux; \
    chmod +x /usr/local/bin/entrypoint /usr/local/bin/php-fpm-healthcheck; \
    mv /etc/supervisor/healthcheck.sh /usr/local/bin/supervisor-healthcheck; \
    chmod +x /usr/local/bin/supervisor-healthcheck

EXPOSE 9000
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm", "--nodaemonize"]


# =============================================================================
# DEVELOPMENT  — source is bind-mounted; Composer + Xdebug available in-image
# =============================================================================
FROM base AS development

ARG UID=1000
ARG GID=1000

ENV APP_ENV=local \
    XDEBUG_MODE=off

COPY --from=composer_bin /usr/bin/composer /usr/local/bin/composer
COPY docker/php/conf.d/development.ini /usr/local/etc/php/conf.d/20-development.ini

RUN set -eux; \
    install-php-extensions xdebug; \
    rm -rf /tmp/*

COPY docker/php/conf.d/xdebug.ini /usr/local/etc/php/conf.d/30-xdebug.ini

USER ${UID}:${GID}

HEALTHCHECK --interval=15s --timeout=5s --start-period=30s --retries=5 \
    CMD ["php-fpm-healthcheck"]

CMD ["php-fpm", "--nodaemonize"]


# =============================================================================
# VENDOR  — resolve PHP dependencies with a cache-friendly layer split
# =============================================================================
FROM base AS vendor

COPY --from=composer_bin /usr/bin/composer /usr/local/bin/composer

# Manifest first: this layer only invalidates when dependencies actually change.
COPY composer.json composer.lock* ./
RUN --mount=type=cache,target=/tmp/composer/cache,sharing=locked \
    set -eux; \
    composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --no-interaction \
        --no-progress \
        --prefer-dist

# Then the source, and finalize the optimized classmap.
COPY . .
RUN --mount=type=cache,target=/tmp/composer/cache,sharing=locked \
    set -eux; \
    composer dump-autoload --no-dev --optimize --classmap-authoritative; \
    composer run-script post-autoload-dump --no-interaction || true


# =============================================================================
# ASSETS  — frontend build (Vite)
# =============================================================================
FROM node:${NODE_VERSION}-bookworm-slim AS assets

WORKDIR /app
ENV npm_config_update_notifier=false

RUN corepack enable

# Frontend assets are optional for API-only Laravel applications.  Docker COPY
# cannot make package.json optional, so copy the source first and decide in the
# shell whether the application actually has a frontend project.  A frontend
# application still gets its normal package-manager-specific, locked install.
COPY . .
RUN --mount=type=cache,target=/root/.npm,sharing=locked \
    set -eux; \
    if [ ! -f package.json ]; then \
        mkdir -p public/build; \
        exit 0; \
    elif [ -f pnpm-lock.yaml ]; then \
        corepack prepare pnpm@latest --activate; \
        pnpm install --frozen-lockfile; \
        pnpm run build; \
    elif [ -f yarn.lock ]; then \
        corepack prepare yarn@stable --activate; \
        yarn install --immutable; \
        yarn build; \
    elif [ -f package-lock.json ]; then \
        npm ci; \
        npm run build; \
    else \
        npm install; \
        npm run build; \
    fi


# =============================================================================
# PRODUCTION  — hardened runtime. No Composer, no Node, no dev tooling.
# =============================================================================
FROM base AS production

ARG UID=1000
ARG GID=1000

ENV APP_ENV=production \
    APP_DEBUG=false

COPY docker/php/conf.d/production.ini /usr/local/etc/php/conf.d/20-production.ini

COPY --chown=${UID}:${GID} --from=vendor /var/www/html     /var/www/html
COPY --chown=${UID}:${GID} --from=assets /app/public/build /var/www/html/public/build

# Writable surface is limited to exactly these two trees.
RUN set -eux; \
    mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        bootstrap/cache; \
    chown -R "${UID}:${GID}" storage bootstrap/cache; \
    chmod -R u+rwX,g+rwX,o-rwx storage bootstrap/cache; \
    \
    # The public/storage symlink is created HERE, at build time, rather than by
    # `artisan storage:link` at boot: public/ lives on the read-only root
    # filesystem in production, so creating it at runtime is impossible. The
    # target path is identical at runtime (storage/ is a volume mounted over
    # the same location), so the link resolves correctly.
    ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage

USER ${UID}:${GID}

HEALTHCHECK --interval=15s --timeout=5s --start-period=20s --retries=5 \
    CMD ["php-fpm-healthcheck"]

CMD ["php-fpm", "--nodaemonize"]
