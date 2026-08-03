# syntax=docker/dockerfile:1.7
# =============================================================================
# Composer 2 — dependency management sidecar
#
# Deliberately built on the PHP image rather than the stock `composer` image:
# platform requirements (ext-intl, ext-gd, ext-redis, the exact PHP version)
# must match the runtime, otherwise Composer resolves a dependency graph that
# is valid here and broken in the app container.
# =============================================================================
ARG PHP_VERSION=8.4
ARG COMPOSER_VERSION=2.8

FROM composer:${COMPOSER_VERSION} AS composer_bin
FROM mlocati/php-extension-installer:2 AS ext_installer

FROM php:${PHP_VERSION}-cli-bookworm

LABEL org.opencontainers.image.title="laravel-composer" \
      org.opencontainers.image.description="Composer 2 with runtime-matched PHP extensions" \
      org.opencontainers.image.licenses="MIT"

ARG UID=1000
ARG GID=1000
ARG USER=app

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_HOME=/tmp/composer \
    COMPOSER_CACHE_DIR=/tmp/composer/cache \
    COMPOSER_NO_INTERACTION=1

WORKDIR /var/www/html

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates git openssh-client unzip zip; \
    rm -rf /var/lib/apt/lists/*

COPY --from=ext_installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer_bin  /usr/bin/composer               /usr/local/bin/composer

# Mirror of the runtime extension set, so `composer install` sees the same
# platform it will actually run on.
RUN set -eux; \
    install-php-extensions \
        bcmath exif gd intl mbstring mysqli opcache pcntl \
        pdo_mysql pdo_pgsql pgsql redis soap sockets zip imagick; \
    rm -rf /tmp/*

RUN set -eux; \
    if ! getent group "${GID}" >/dev/null; then groupadd --gid "${GID}" "${USER}"; fi; \
    if ! getent passwd "${UID}" >/dev/null; then \
        useradd --uid "${UID}" --gid "${GID}" --create-home --shell /bin/bash "${USER}"; \
    fi; \
    mkdir -p "${COMPOSER_CACHE_DIR}" /var/www/html; \
    chown -R "${UID}:${GID}" /tmp/composer /var/www/html

USER ${UID}:${GID}

ENTRYPOINT ["composer"]
CMD ["--version"]
